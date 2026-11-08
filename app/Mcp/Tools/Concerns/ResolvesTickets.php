<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Ticket;
use App\Models\User;

trait ResolvesTickets
{
    /**
     * The ticket the user can see for a `tkt_…` id or a number (`123` / `#123`), or null.
     */
    protected function resolveTicket(User $user, string $reference): ?Ticket
    {
        $reference = trim($reference);
        $query = Ticket::visibleTo($user);

        if (preg_match('/^#?(\d{1,9})$/', $reference, $matches)) {
            return $query->where('number', (int) $matches[1])->first();
        }

        return $query->whereKey($reference)->first();
    }
}
