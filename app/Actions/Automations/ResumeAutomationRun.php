<?php

namespace App\Actions\Automations;

use App\Automations\Context;
use App\Automations\Engine;
use App\Enums\AutomationRunStatus;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Ticket;
use App\Models\TicketEvent;

class ResumeAutomationRun
{
    public function __construct(protected Engine $engine) {}

    /**
     * Continue a run that {@see ResumeDueAutomationRuns} claimed, from its definition snapshot. It is
     * cancelled instead when its ticket is deleted, its automation is gone or disabled, or the ticket
     * left the automation's desk. Resuming is not a new trigger: the run keeps its depth and takes no rate-limit attempt.
     */
    public function handle(AutomationRun $run): void
    {
        $run = AutomationRun::query()->find($run->id);

        if ($run === null || $run->status !== AutomationRunStatus::RUNNING || $run->resume_at === null) {
            return;
        }

        $automation = Automation::query()->find($run->automation_id);
        $ticket = Ticket::withTrashed()->find($run->ticket_id);

        $reason = match (true) {
            $ticket === null || $ticket->trashed() => __('The ticket was deleted.'),
            $automation === null || ! $automation->enabled => __('The automation was disabled or deleted.'),
            $automation->desk_id !== null && $automation->desk_id !== $ticket->desk_id => __('The ticket left the automation\'s desk.'),
            default => null,
        };

        if ($reason !== null) {
            $run->forceFill([
                'status' => AutomationRunStatus::CANCELLED,
                'resume_at' => null,
                'log' => [...$run->log, ['type' => 'cancelled', 'reason' => $reason]],
            ])->save();

            return;
        }

        $event = $run->ticket_event_id === null ? null : TicketEvent::query()->find($run->ticket_event_id);

        $this->engine->resume($automation, new Context($ticket, $event, run: $run), $run);
    }
}
