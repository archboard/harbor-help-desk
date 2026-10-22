<?php

namespace App\Actions\Automations;

use App\Automations\Context;
use App\Automations\Engine;
use App\Enums\AutomationRunStatus;
use App\Enums\TicketStatus;
use App\Models\Automation;
use App\Models\Ticket;

class RunScheduledAutomation
{
    public function __construct(protected Engine $engine, protected StartAutomationRun $startRun) {}

    /**
     * Run a swept automation on the ticket: the whole definition, with no triggering event, so the
     * leading conditions are checked again against the ticket as it is now. Does nothing when the
     * automation was disabled, the ticket was deleted or closed, or it left the automation's desk
     * since the sweep. Takes a rate-limit attempt like any new run.
     */
    public function handle(Automation $automation, Ticket $ticket): void
    {
        $automation = Automation::query()->whereKey($automation->getKey())->first();
        $ticket = Ticket::query()->whereKey($ticket->getKey())->first();

        if ($automation === null || ! $automation->enabled || $ticket === null || $ticket->status === TicketStatus::CLOSED) {
            return;
        }

        if ($automation->desk_id !== null && $automation->desk_id !== $ticket->desk_id) {
            return;
        }

        $run = $this->startRun->handle($automation, new Context($ticket));

        if ($run->status !== AutomationRunStatus::SKIPPED) {
            $this->engine->run($automation, new Context($ticket, run: $run), $run);
        }
    }
}
