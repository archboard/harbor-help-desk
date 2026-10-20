<?php

namespace App\Actions\Tickets;

use App\Data\Tickets\UpdateTicketCommentData;
use App\Enums\TicketEventType;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateTicketComment
{
    /**
     * Edit the comment's body. The previous body is kept in the ticket's audit trail.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, TicketComment $comment, array $input): TicketComment
    {
        Gate::forUser($actor)->authorize('update', $comment);

        $validated = UpdateTicketCommentData::validateAndCreate($input)->filled();

        if ($validated['body'] === $comment->body) {
            return $comment;
        }

        DB::transaction(function () use ($actor, $comment, $validated): void {
            $comment->ticket->recordEvent(TicketEventType::COMMENT_EDITED, $actor, ['comment_id' => $comment->id, 'from' => $comment->body]);
            $comment->update(['body' => $validated['body'], 'edited_at' => now()]);
        });

        return $comment;
    }
}
