<?php

namespace App\Policies;

use App\Models\TicketReminder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * People cancel their own reminders. Cancelling someone else's ticket-level reminder takes
 * the same rights as deleting the ticket.
 */
class TicketReminderPolicy
{
    public function delete(User $user, TicketReminder $reminder): bool
    {
        if ($reminder->created_by === $user->getKey()) {
            return true;
        }

        return $reminder->isTicketLevel() && Gate::forUser($user)->allows('delete', $reminder->ticket);
    }
}
