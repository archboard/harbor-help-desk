<?php

namespace App\Listeners;

use App\Enums\TicketEventType;
use App\Events\TicketActivityRecorded;
use App\Events\TicketChanged;
use App\Models\User;

/**
 * Turn recorded ticket activity into a realtime ping. Not a queued listener on purpose:
 * it only builds the event, and {@see TicketChanged} is queued on `broadcasts` itself.
 * The window that made the change (its `X-Socket-ID`) is excluded; queued and console
 * contexts have no socket, so everyone gets the ping.
 */
class BroadcastTicketChange
{
    public function handle(TicketActivityRecorded $event): void
    {
        $ticketEvent = $event->ticketEvent;
        $moved = $ticketEvent->type === TicketEventType::DESK_CHANGED;

        broadcast(new TicketChanged(
            $ticketEvent->ticket_id,
            $ticketEvent->ticket->desk_id,
            $ticketEvent->type,
            $ticketEvent->actor instanceof User ? $ticketEvent->actor->getKey() : null,
            $moved ? $ticketEvent->properties['from_id'] ?? null : null,
        ))->toOthers();
    }
}
