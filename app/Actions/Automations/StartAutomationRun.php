<?php

namespace App\Actions\Automations;

use App\Automations\Context;
use App\Enums\AutomationRunStatus;
use App\Models\Automation;
use App\Models\AutomationRun;
use Illuminate\Support\Facades\RateLimiter;

class StartAutomationRun
{
    /**
     * Record the run for the context's ticket (and event, when it has one), already `skipped`
     * when a loop limit is hit: the chain depth or the ticket's runs per hour.
     */
    public function handle(Automation $automation, Context $context, ?AutomationRun $parentRun = null): AutomationRun
    {
        $depth = $parentRun === null ? 0 : $parentRun->depth + 1;

        $skipReason = match (true) {
            $depth > RunAutomationsForEvent::MAX_DEPTH => __('Automations triggered each other more than :max levels deep.', ['max' => RunAutomationsForEvent::MAX_DEPTH]),
            ! RateLimiter::attempt("automations:ticket:{$context->ticket->getKey()}", RunAutomationsForEvent::MAX_RUNS_PER_HOUR, fn () => true, 3600) => __('This ticket reached :max automation runs in an hour.', ['max' => RunAutomationsForEvent::MAX_RUNS_PER_HOUR]),
            default => null,
        };

        return AutomationRun::query()->create([
            'workspace_id' => $context->ticket->workspace_id,
            'automation_id' => $automation->getKey(),
            'ticket_id' => $context->ticket->getKey(),
            'ticket_event_id' => $context->event?->getKey(),
            'parent_run_id' => $parentRun?->getKey(),
            'depth' => $depth,
            'status' => $skipReason === null ? AutomationRunStatus::RUNNING : AutomationRunStatus::SKIPPED,
            'definition' => $automation->definition,
            'log' => $skipReason === null ? [] : [['type' => 'skipped', 'reason' => $skipReason]],
            'state' => $context->payload === [] ? [] : ['payload' => $context->payload],
        ]);
    }
}
