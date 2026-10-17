<?php

namespace App\Policies;

use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Agents who can work a ticket can edit their own comments on it. Deleting someone else's
 * comment takes the same rights as deleting the ticket.
 */
class TicketCommentPolicy
{
    public function update(User $user, TicketComment $comment): bool
    {
        return $comment->isWrittenBy($user) && Gate::forUser($user)->allows('update', $comment->ticket);
    }

    public function delete(User $user, TicketComment $comment): bool
    {
        return $this->update($user, $comment) || Gate::forUser($user)->allows('delete', $comment->ticket);
    }
}
