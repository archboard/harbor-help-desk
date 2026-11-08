<?php

namespace App\Actions\Tickets;

use App\Enums\AutomationRunStatus;
use App\Enums\TicketEventType;
use App\Models\AutomationRun;
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

        $this->trash($ticket, $actor);
    }

    /**
     * Soft-delete without authorizing; a null actor is the system (retention).
     */
    public function trash(Ticket $ticket, ?User $actor): void
    {
        DB::transaction(function () use ($actor, $ticket): void {
            $ticket->delete();
            AutomationRun::query()->where('ticket_id', $ticket->getKey())->where('status', AutomationRunStatus::WAITING)->get()
                ->each(fn (AutomationRun $run) => $run->cancelWaiting(__('The ticket was deleted.')));
            $ticket->recordEvent(TicketEventType::DELETED, $actor);
        });
    }
}
