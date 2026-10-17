<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RestoreTicket
{
    public function handle(User $actor, Ticket $ticket): Ticket
    {
        Gate::forUser($actor)->authorize('restore', $ticket);

        DB::transaction(function () use ($actor, $ticket): void {
            $ticket->restore();
            $ticket->recordEvent(TicketEventType::RESTORED, $actor);
        });

        return $ticket;
    }
}
