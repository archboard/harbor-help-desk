<?php

namespace App\Listeners;

use App\Actions\Tickets\SyncTicketSlaTargets as SyncTicketSlaTargetsAction;
use App\Events\TicketActivityRecorded;

/**
 * Keep a ticket's SLA clocks in step with what happened to it.
 *
 * Deliberately not queued: several events from one save have to apply in order, and this only
 * does a few row writes.
 * ponytail: if it ever slows requests, queue it on a per-ticket unique job.
 */
class SyncTicketSlaTargets
{
    public function __construct(protected SyncTicketSlaTargetsAction $syncTicketSlaTargets) {}

    public function handle(TicketActivityRecorded $event): void
    {
        $ticket = $event->ticketEvent->ticket;

        if ($ticket !== null) {
            $this->syncTicketSlaTargets->handle($ticket, $event->ticketEvent);
        }
    }
}
