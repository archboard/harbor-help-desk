<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteTicket
{
    /**
     * Soft-delete the ticket so it can be restored.
     */
    public function handle(User $actor, Ticket $ticket): void
    {
        Gate::forUser($actor)->authorize('delete', $ticket);

        DB::transaction(function () use ($actor, $ticket): void {
            $ticket->delete();
            $ticket->recordEvent(TicketEventType::DELETED, $actor);
        });
    }
}
