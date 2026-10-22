<?php

namespace App\Events;

use App\Models\TicketEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new ticket, or one moved to another desk, has been through the automations and the desk's
 * assignment strategy. Dispatched by the automations job once both have run, so listeners see
 * the final assignees. `$ticketEvent` is the `created` or `desk_changed` event that was triaged.
 */
class TicketTriaged
{
    use Dispatchable, SerializesModels;

    public function __construct(public TicketEvent $ticketEvent) {}
}
