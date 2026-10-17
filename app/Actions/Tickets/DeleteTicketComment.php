<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteTicketComment
{
    /**
     * Soft-delete the comment, so its body stays available for audits.
     */
    public function handle(User $actor, TicketComment $comment): void
    {
        Gate::forUser($actor)->authorize('delete', $comment);

        DB::transaction(function () use ($actor, $comment): void {
            $comment->delete();
            $comment->ticket->recordEvent(TicketEventType::COMMENT_DELETED, $actor, ['comment_id' => $comment->id]);
        });
    }
}
