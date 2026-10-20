<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\TicketEvent;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Something happened to a ticket. Dispatched by {@see Ticket::recordEvent()} once
 * the surrounding transaction commits, so a rolled-back Action notifies nobody. Listeners filter
 * on `$ticketEvent->type`, and one save can record several events.
 */
class TicketActivityRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public TicketEvent $ticketEvent) {}
}
