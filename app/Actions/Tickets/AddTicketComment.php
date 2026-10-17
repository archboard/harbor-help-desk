<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class AddTicketComment
{
    public function __construct(protected AddTicketAttachment $addTicketAttachment) {}

    /**
     * Reply on the ticket, or add an internal note when `is_internal` is true. Files in
     * `attachments` are attached to the comment.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Ticket $ticket, array $input): TicketComment
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        $validated = Validator::make($input, [
            'body' => ['required', 'string', 'max:65535'],
            'is_internal' => ['sometimes', 'boolean'],
            'attachments' => ['sometimes', 'array', 'max:20'],
            'attachments.*' => AddTicketAttachment::rules(),
        ])->validate();

        return DB::transaction(function () use ($actor, $ticket, $validated): TicketComment {
            $comment = new TicketComment([
                'body' => $validated['body'],
                'is_internal' => $validated['is_internal'] ?? false,
            ]);
            $comment->author()->associate($actor);
            $ticket->comments()->save($comment);

            $ticket->recordEvent(
                $comment->is_internal ? TicketEventType::INTERNAL_NOTE_ADDED : TicketEventType::COMMENT_ADDED,
                $actor,
                ['comment_id' => $comment->id],
            );

            // ponytail: every file is validated up front, so only a storage error mid-loop rolls back
            // the comment; files already written then stay on disk. Add an orphan sweep if that bites.
            foreach ($validated['attachments'] ?? [] as $file) {
                $this->addTicketAttachment->handle($actor, $ticket, ['file' => $file], $comment);
            }

            return $comment;
        });
    }
}
