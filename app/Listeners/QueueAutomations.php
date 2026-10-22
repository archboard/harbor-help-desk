<?php

namespace App\Listeners;

use App\Events\TicketActivityRecorded;
use App\Jobs\RunAutomations;

/**
 * Hand recorded ticket activity to the automations queue. Which automations match is decided
 * in the job, inside the event's workspace.
 */
class QueueAutomations
{
    public function handle(TicketActivityRecorded $event): void
    {
        RunAutomations::dispatch($event->ticketEvent);
    }
}
