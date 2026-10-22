<?php

namespace App\Actions\Tickets;

use App\Data\Tickets\AddTicketCommentData;
use App\Enums\TicketEventType;
use App\Jobs\SendTicketReplyEmail;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AddTicketComment
{
    public function __construct(protected AddTicketAttachment $addTicketAttachment) {}

    /**
     * Reply on the ticket, or add an internal note when `is_internal` is true. Files in
     * `attachments` are attached to the comment. An agent's public reply is emailed to the
     * requester once the transaction commits. An automation acts like an agent, without the policy
     * check or the `last_seen_comment_id` and mention options. A contact (replying by email) skips
     * the policy check, can't add internal notes, and alone may set `message_id` (the email's Message-ID).
     * A user may send `last_seen_comment_id` (null when none were shown): if anyone else commented
     * after it, the reply is rejected so two people don't answer at once. Omitting it skips the check.
     * An internal note's `mentioned_user_ids` (desk members) are stored on its event.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User|Contact|Automation $actor, Ticket $ticket, array $input): TicketComment
    {
        if ($actor instanceof User) {
            Gate::forUser($actor)->authorize('update', $ticket);
        }

        $validated = AddTicketCommentData::validateAndCreateWith($input, [
            'is_internal' => [! $actor instanceof Contact ? 'sometimes' : 'prohibited'],
            'message_id' => [$actor instanceof Contact ? 'sometimes' : 'prohibited'],
            'last_seen_comment_id' => [$actor instanceof User ? 'sometimes' : 'prohibited'],
            'mentioned_user_ids' => [$actor instanceof User && filter_var($input['is_internal'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'sometimes' : 'prohibited'],
            'mentioned_user_ids.*' => [Rule::in($ticket->desk->users()->pluck('users.id')->all())],
        ])->filled();

        return DB::transaction(function () use ($actor, $ticket, $validated): TicketComment {
            if ($actor instanceof User && array_key_exists('last_seen_comment_id', $validated)) {
                $this->ensureNoNewReplies($actor, $ticket, $validated['last_seen_comment_id']);
            }

            $comment = new TicketComment([
                'body' => $validated['body'],
                'is_internal' => $validated['is_internal'] ?? false,
                'message_id' => $validated['message_id'] ?? null,
            ]);
            $comment->author()->associate($actor);
            $ticket->comments()->save($comment);

            $ticket->recordEvent(
                $comment->is_internal ? TicketEventType::INTERNAL_NOTE_ADDED : TicketEventType::COMMENT_ADDED,
                $actor,
                ['comment_id' => $comment->id] + ($comment->is_internal && filled($validated['mentioned_user_ids'] ?? null)
                    ? ['mentioned_user_ids' => $validated['mentioned_user_ids']]
                    : []),
            );

            // ponytail: every file is validated up front, so only a storage error mid-loop rolls back
            // the comment; files already written then stay on disk. Add an orphan sweep if that bites.
            foreach ($validated['attachments'] ?? [] as $file) {
                $this->addTicketAttachment->handle($actor, $ticket, ['file' => $file], $comment);
            }

            if (! $actor instanceof Contact && ! $comment->is_internal) {
                SendTicketReplyEmail::dispatch($comment);
            }

            return $comment;
        });
    }

    /**
     * @throws ValidationException
     */
    protected function ensureNoNewReplies(User $actor, Ticket $ticket, ?string $lastSeenCommentId): void
    {
        $newest = $ticket->comments()
            ->when($lastSeenCommentId, fn ($query) => $query->where('id', '>', $lastSeenCommentId))
            ->whereNot(fn ($query) => $query->whereMorphedTo('author', $actor))
            ->with('author')
            ->reorder()
            ->latest('id')
            ->first();

        if ($newest) {
            throw ValidationException::withMessages([
                'last_seen_comment_id' => __(':name replied while you were writing.', ['name' => $newest->author->getAttribute('name')]),
            ]);
        }
    }
}
