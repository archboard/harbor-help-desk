<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MoveTicket
{
    /**
     * Move the ticket to another desk. The actor needs access to both desks.
     */
    public function handle(User $actor, Ticket $ticket, Desk $desk): Ticket
    {
        Gate::forUser($actor)->authorize('update', $ticket);
        Gate::forUser($actor)->authorize('create', [Ticket::class, $desk]);

        if ($ticket->desk_id === $desk->getKey()) {
            return $ticket;
        }

        DB::transaction(function () use ($actor, $ticket, $desk): void {
            $ticket->recordEvent(TicketEventType::DESK_CHANGED, $actor, [
                'from' => $ticket->desk->name,
                'to' => $desk->name,
                'from_id' => $ticket->desk_id,
                'to_id' => $desk->getKey(),
            ]);
            $ticket->desk()->associate($desk)->save();
        });

        return $ticket;
    }
}
