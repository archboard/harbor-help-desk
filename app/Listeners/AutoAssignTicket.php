<?php

namespace App\Listeners;

use App\Actions\Tickets\AutoAssignTicket as AutoAssignTicketAction;
use App\Enums\TicketEventType;
use App\Events\TicketActivityRecorded;

/**
 * Assign new tickets, and tickets moved onto a desk, as the desk's strategy says.
 *
 * Deliberately not queued: the event dispatches after commit, so running synchronously assigns the
 * ticket before the queued `new_ticket` notification checks that it is still unassigned.
 * ponytail: if picking gets slow, queue it and have `new_ticket` skip desks that use a strategy.
 *
 * Ordering contract for the rules engine: Laravel doesn't guarantee the order of auto-discovered
 * listeners, so nothing may rely on running before or after this one. Phase 3.6 step A6 removes
 * this listener, calls {@see AutoAssignTicketAction} from the automation job after the rules run,
 * then dispatches `TicketTriaged`.
 */
class AutoAssignTicket
{
    public function __construct(protected AutoAssignTicketAction $autoAssignTicket) {}

    public function handle(TicketActivityRecorded $event): void
    {
        if (! in_array($event->ticketEvent->type, [TicketEventType::CREATED, TicketEventType::DESK_CHANGED], true)) {
            return;
        }

        // Deleted before the listener ran.
        $ticket = $event->ticketEvent->ticket;

        if ($ticket !== null) {
            $this->autoAssignTicket->handle($ticket);
        }
    }
}
