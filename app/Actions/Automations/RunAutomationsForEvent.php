<?php

namespace App\Actions\Automations;

use App\Automations\AutomationRegistry;
use App\Automations\Context;
use App\Automations\Engine;
use App\Enums\AutomationRunStatus;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Ticket;
use App\Models\TicketEvent;
use Illuminate\Support\Facades\RateLimiter;

class RunAutomationsForEvent
{
    /** How many runs deep automations may trigger each other. */
    public const int MAX_DEPTH = 5;

    /** How many runs one ticket may get per hour. */
    public const int MAX_RUNS_PER_HOUR = 100;

    public function __construct(protected AutomationRegistry $registry, protected Engine $engine) {}

    /**
     * Run the enabled automations whose trigger matches the event, in position order, for the
     * ticket as it is when each one starts. A failed automation doesn't stop the others; a stop does.
     *
     * Loop protection: an automation ignores events its own runs caused, a chain of runs triggering
     * runs ends at {@see self::MAX_DEPTH}, and one ticket gets at most {@see self::MAX_RUNS_PER_HOUR}
     * runs an hour. The last two log a `skipped` run with the reason.
     */
    public function handle(TicketEvent $event): void
    {
        $ticket = $event->ticket;

        if ($ticket === null) {
            return;
        }

        $this->cancelWaitingRuns($event, $ticket);

        $parentRun = $event->automation_run_id === null ? null : AutomationRun::query()->find($event->automation_run_id);

        foreach (Automation::query()->where('enabled', true)->orderBy('position')->orderBy('id')->get() as $automation) {
            if ($parentRun?->automation_id === $automation->getKey()) {
                continue;
            }

            if (! $this->appliesTo($automation, $ticket, $event)) {
                continue;
            }

            $context = new Context($ticket, $event);
            $run = $this->start($automation, $context, $parentRun);

            if ($run->status === AutomationRunStatus::SKIPPED) {
                continue;
            }

            $context = new Context($ticket, $event, run: $run);

            if ($this->engine->run($automation, $context, $run)->state['stopped'] ?? false) {
                break;
            }
        }
    }

    /**
     * Cancel the ticket's waiting runs whose delay lists a trigger type that this event is one of.
     * A `cancel_on` entry is matched as that trigger with its default config (any author, any
     * visibility, any field), since a delay stores trigger keys only. An event a run caused itself never cancels it.
     */
    protected function cancelWaitingRuns(TicketEvent $event, Ticket $ticket): void
    {
        AutomationRun::query()->where('ticket_id', $ticket->getKey())->where('status', AutomationRunStatus::WAITING)
            ->whereKeyNot($event->automation_run_id ?? '')->get()
            ->each(function (AutomationRun $run) use ($event): void {
                foreach ($run->state['cancel_on'] ?? [] as $key) {
                    if ($this->registry->trigger($key)?->matches($event, []) === true) {
                        $run->cancelWaiting(__('Cancelled by a :trigger event.', ['trigger' => $key]));

                        return;
                    }
                }
            });
    }

    /**
     * The trigger is checked first, as it needs only the event. The desk scope is checked against
     * the ticket's current desk, which an earlier automation may have changed.
     */
    protected function appliesTo(Automation $automation, Ticket $ticket, TicketEvent $event): bool
    {
        if ($this->registry->trigger($automation->trigger_type)?->matches($event, $automation->definition->trigger->config) !== true) {
            return false;
        }

        return $automation->desk_id === null
            || $automation->desk_id === Ticket::withTrashed()->whereKey($ticket->getKey())->value('desk_id');
    }

    /**
     * Record the run, already `skipped` when a loop limit is hit.
     */
    protected function start(Automation $automation, Context $context, ?AutomationRun $parentRun): AutomationRun
    {
        $depth = $parentRun === null ? 0 : $parentRun->depth + 1;

        $skipReason = match (true) {
            $depth > self::MAX_DEPTH => __('Automations triggered each other more than :max levels deep.', ['max' => self::MAX_DEPTH]),
            ! RateLimiter::attempt($this->rateLimitKey($context->ticket), self::MAX_RUNS_PER_HOUR, fn () => true, 3600) => __('This ticket reached :max automation runs in an hour.', ['max' => self::MAX_RUNS_PER_HOUR]),
            default => null,
        };

        return AutomationRun::query()->create([
            'workspace_id' => $context->ticket->workspace_id,
            'automation_id' => $automation->getKey(),
            'ticket_id' => $context->ticket->getKey(),
            'ticket_event_id' => $context->event->getKey(),
            'parent_run_id' => $parentRun?->getKey(),
            'depth' => $depth,
            'status' => $skipReason === null ? AutomationRunStatus::RUNNING : AutomationRunStatus::SKIPPED,
            'definition' => $automation->definition,
            'log' => $skipReason === null ? [] : [['type' => 'skipped', 'reason' => $skipReason]],
            'state' => [],
        ]);
    }

    protected function rateLimitKey(Ticket $ticket): string
    {
        return "automations:ticket:{$ticket->getKey()}";
    }
}
