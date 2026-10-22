<?php

namespace App\Actions\Automations;

use App\Automations\ConditionEvaluator;
use App\Automations\Context;
use App\Data\Automations\ConditionStepData;
use App\Enums\TicketStatus;
use App\Jobs\RunScheduledAutomation;
use App\Models\Automation;
use App\Models\AutomationTicketMatch;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SweepScheduledAutomations
{
    public function __construct(protected ConditionEvaluator $conditions) {}

    /**
     * Sweep the current workspace's `schedule` automations that are due: each claims its latest
     * scheduled moment, then queues a {@see RunScheduledAutomation} job for every ticket in scope
     * that passes its leading conditions and hasn't fired yet (or not within `refire_after_days`).
     * Tickets that don't pass get no run, so a sweep doesn't flood the run log.
     *
     * "In scope" are the workspace's tickets, or the automation's desk's, that aren't deleted or
     * closed. Solved tickets are included, so an automation can close them; its conditions say
     * which statuses it wants.
     *
     * ponytail: conditions are evaluated in PHP per ticket, so the ceiling is roughly tens of
     * thousands of open tickets per sweep. The upgrade path is to compile simple conditions into SQL `where`s.
     *
     * @return int How many tickets were queued.
     */
    public function handle(): int
    {
        $queued = 0;

        foreach (Automation::query()->where('enabled', true)->where('trigger_type', 'schedule')->orderBy('position')->orderBy('id')->get() as $automation) {
            $claimed = $this->claim($automation);

            if ($claimed !== null) {
                $queued += $this->sweep($claimed);
            }
        }

        return $queued;
    }

    /**
     * Mark the automation's latest moment as swept under a row lock, so two commands never sweep
     * the same moment.
     */
    protected function claim(Automation $automation): ?Automation
    {
        return DB::transaction(function () use ($automation): ?Automation {
            $locked = Automation::query()->whereKey($automation->getKey())->lock('for update')->first();
            $moment = $locked?->dueSweepMoment();

            if ($locked === null || $moment === null) {
                return null;
            }

            $locked->timestamps = false;
            $locked->update(['last_swept_at' => $moment]);

            return $locked;
        });
    }

    protected function sweep(Automation $automation): int
    {
        $config = $automation->definition->trigger->config;
        $refireAfterDays = $config['refire_after_days'] ?? null;
        $leading = collect($automation->definition->steps)->takeWhile(fn ($step): bool => $step instanceof ConditionStepData)->whereInstanceOf(ConditionStepData::class);
        $queued = 0;

        Ticket::query()
            ->where('status', '!=', TicketStatus::CLOSED)
            ->when($automation->desk_id, fn ($query, string $deskId) => $query->where('desk_id', $deskId))
            ->chunkById(200, function (Collection $tickets) use ($automation, $leading, $refireAfterDays, &$queued): void {
                foreach ($tickets as $ticket) {
                    if ($this->passes($leading, $ticket) && $this->recordMatch($automation, $ticket, $refireAfterDays)) {
                        RunScheduledAutomation::dispatch($automation, $ticket);
                        $queued++;
                    }
                }
            });

        return $queued;
    }

    /**
     * @param  Collection<int, ConditionStepData>  $conditions
     */
    protected function passes(Collection $conditions, Ticket $ticket): bool
    {
        $context = new Context($ticket);

        return $conditions->every(fn (ConditionStepData $step): bool => $this->conditions->evaluate($step->match, $step->rules, $context)['passed']);
    }

    /**
     * Record that the automation fires for the ticket now, in one statement so concurrent sweeps
     * can't both win: a new pair is inserted, an existing one is refreshed only when
     * `refire_after_days` have passed since it fired, and with no refire an existing one is left alone.
     *
     * @return bool Whether the automation may fire.
     */
    protected function recordMatch(Automation $automation, Ticket $ticket, ?int $refireAfterDays): bool
    {
        $match = new AutomationTicketMatch;
        $now = now();

        return DB::affectingStatement(
            'insert into automation_ticket_matches (id, workspace_id, automation_id, ticket_id, fired_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?) '
            .'on conflict (workspace_id, automation_id, ticket_id) '
            .($refireAfterDays === null ? 'do nothing' : 'do update set fired_at = excluded.fired_at, updated_at = excluded.updated_at where automation_ticket_matches.fired_at <= ?'),
            [$match->newUniqueId(), $ticket->workspace_id, $automation->getKey(), $ticket->getKey(), $now, $now, $now, ...($refireAfterDays === null ? [] : [$now->subDays($refireAfterDays)])],
        ) > 0;
    }
}
