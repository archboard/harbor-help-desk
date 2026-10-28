<?php

namespace App\Actions\Reports;

use App\Data\Reports\KpiData;
use App\Data\Reports\SlaMetricReportData;
use App\Data\Reports\SlaPriorityData;
use App\Data\Reports\SlaReportData;
use App\Enums\SlaMetric;
use App\Enums\TicketPriority;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildSlaReport
{
    /**
     * Met, breached and in-progress targets of tickets created in the period, by metric and by the
     * ticket's priority now, and breaches over time.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $viewer, array $input): SlaReportData
    {
        $scope = ReportScope::resolve($viewer, $input);
        $current = $this->outcomes($scope, false);
        $previous = $this->outcomes($scope, true);

        return new SlaReportData(
            period: $scope->period(),
            metrics: collect(SlaMetric::cases())->map(function (SlaMetric $metric) use ($current, $previous): SlaMetricReportData {
                $rows = $current->where('metric', $metric->value);
                $met = (int) $rows->sum('met');
                $breached = (int) $rows->sum('breached');
                $previousRows = $previous->where('metric', $metric->value);

                return new SlaMetricReportData(
                    metric: $metric,
                    met: $met,
                    breached: $breached,
                    inProgress: (int) $rows->sum('in_progress'),
                    percentMet: new KpiData($this->percentMet($met, $breached), $this->percentMet((int) $previousRows->sum('met'), (int) $previousRows->sum('breached'))),
                    byPriority: collect(TicketPriority::cases())->map(function (TicketPriority $priority) use ($rows): SlaPriorityData {
                        $row = $rows->firstWhere('priority', $priority->value);

                        return new SlaPriorityData($priority, (int) ($row->met ?? 0), (int) ($row->breached ?? 0), $this->percentMet((int) ($row->met ?? 0), (int) ($row->breached ?? 0)));
                    })->all(),
                );
            })->all(),
            breaches: $scope->series(collect(SlaMetric::cases())->mapWithKeys(fn (SlaMetric $metric): array => [
                $metric->value => $scope->perBucket(
                    $scope->during($scope->slaTargets(), 'ticket_sla_targets.breached_at')->where('ticket_sla_targets.metric', $metric),
                    'ticket_sla_targets.breached_at',
                    'count(*)',
                ),
            ])->all(), empty: 0),
        );
    }

    /**
     * One row per metric and priority, with `met`, `breached` and `in_progress` counts.
     *
     * @return Collection<int, \stdClass>
     */
    protected function outcomes(ReportScope $scope, bool $previous): Collection
    {
        return $scope->during($scope->slaTargets(), 'ticket_sla_targets.started_at', $previous)
            ->toBase()
            ->selectRaw('ticket_sla_targets.metric, tickets.priority, count(*) filter (where ticket_sla_targets.breached_at is null and ticket_sla_targets.achieved_at is not null) as met, count(*) filter (where ticket_sla_targets.breached_at is not null) as breached, count(*) filter (where ticket_sla_targets.breached_at is null and ticket_sla_targets.achieved_at is null) as in_progress')
            ->groupBy('ticket_sla_targets.metric', 'tickets.priority')
            ->get();
    }

    protected function percentMet(int $met, int $breached): ?float
    {
        return $met + $breached === 0 ? null : ReportScope::round($met / ($met + $breached) * 100);
    }
}
