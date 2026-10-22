<?php

namespace App\Actions\Automations;

use App\Automations\Context;
use App\Automations\Engine;
use App\Enums\AutomationRunStatus;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Ticket;

class RunWebhookAutomation
{
    public function __construct(protected Engine $engine) {}

    /**
     * Run a received webhook's automation from its definition snapshot, with the payload kept in
     * `state.payload`. The run is cancelled instead when the automation was disabled or deleted, or
     * the ticket was deleted or left the automation's desk since the webhook arrived.
     */
    public function handle(AutomationRun $run): void
    {
        $run = AutomationRun::query()->whereKey($run->getKey())->first();

        if ($run === null || $run->status !== AutomationRunStatus::RUNNING || $run->ticket_id === null) {
            return;
        }

        $automation = Automation::query()->find($run->automation_id);
        $ticket = Ticket::query()->find($run->ticket_id);

        $reason = match (true) {
            $ticket === null => __('The ticket was deleted.'),
            $automation === null || ! $automation->enabled => __('The automation was disabled or deleted.'),
            $automation->desk_id !== null && $automation->desk_id !== $ticket->desk_id => __('The ticket left the automation\'s desk.'),
            default => null,
        };

        if ($reason !== null) {
            $run->forceFill([
                'status' => AutomationRunStatus::CANCELLED,
                'log' => [...$run->log, ['type' => 'cancelled', 'reason' => $reason]],
            ])->save();

            return;
        }

        $this->engine->run($automation, new Context($ticket, null, $run->state['payload'] ?? [], $run), $run);
    }
}
