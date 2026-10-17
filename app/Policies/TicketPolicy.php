<?php

namespace App\Policies;

use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Anyone who can see a ticket's desk can work its tickets. Deleting a ticket takes the
 * same rights as managing its desk.
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', Desk::class);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return Gate::forUser($user)->allows('view', $ticket->desk);
    }

    public function create(User $user, Desk $desk): bool
    {
        return Gate::forUser($user)->allows('view', $desk);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return Gate::forUser($user)->allows('update', $ticket->desk);
    }

    public function restore(User $user, Ticket $ticket): bool
    {
        return $this->delete($user, $ticket);
    }
}
