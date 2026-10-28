<?php

namespace App\Actions\Reports;

use App\Data\Reports\BreakdownData;
use App\Data\Reports\HeatmapCellData;
use App\Data\Reports\OverviewReportData;
use App\Enums\TicketEventType;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BuildOverviewReport
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $viewer, array $input): OverviewReportData
    {
        $scope = ReportScope::resolve($viewer, $input);

        $kpis = $scope->kpis(fn (bool $previous): array => [
            'created' => $scope->during($scope->tickets(), 'tickets.created_at', $previous)->count(),
            'resolved' => $scope->during($scope->metrics(), 'ticket_metrics.resolved_at', $previous)->count(),
            'reopened' => $scope->during($this->reopens($scope), 'ticket_events.created_at', $previous)->count(),
        ]);

        $created = $scope->during($scope->tickets(), 'tickets.created_at');

        return new OverviewReportData(
            period: $scope->period(),
            created: $kpis['created'],
            resolved: $kpis['resolved'],
            reopened: $kpis['reopened'],
            backlog: $this->backlog($scope),
            volume: $scope->series([
                'created' => $scope->perBucket(clone $created, 'tickets.created_at', 'count(*)'),
                'resolved' => $scope->perBucket($scope->during($scope->metrics(), 'ticket_metrics.resolved_at'), 'ticket_metrics.resolved_at', 'count(*)'),
            ], empty: 0),
            heatmap: $this->heatmap($scope, clone $created),
            bySource: $this->bySource(clone $created),
            byDesk: $this->byDesk($created),
        );
    }

    /**
     * Moves from a resolved status back to an unresolved one.
     *
     * @return Builder<TicketEvent>
     */
    protected function reopens(ReportScope $scope): Builder
    {
        $resolved = collect(TicketStatus::cases())->filter->isResolved()->map->value->values()->all();

        return $scope->events()
            ->where('ticket_events.type', TicketEventType::STATUS_CHANGED)
            ->whereIn('ticket_events.properties->from', $resolved)
            ->whereNotIn('ticket_events.properties->to', $resolved);
    }

    /**
     * @return array<int, BreakdownData>
     */
    protected function backlog(ReportScope $scope): array
    {
        $counts = $scope->tickets()->toBase()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        return collect(TicketStatus::cases())
            ->reject->isResolved()
            ->map(fn (TicketStatus $status): BreakdownData => new BreakdownData($status->value, $status->label(), (int) ($counts[$status->value] ?? 0)))
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Ticket>  $created
     * @return array<int, HeatmapCellData>
     */
    protected function heatmap(ReportScope $scope, Builder $created): array
    {
        return $created->toBase()
            ->selectRaw('extract(isodow from tickets.created_at at time zone ?)::int as weekday, extract(hour from tickets.created_at at time zone ?)::int as hour, count(*) as count', [$scope->timezone, $scope->timezone])
            ->groupBy('weekday', 'hour')
            ->orderBy('weekday')->orderBy('hour')
            ->get()
            ->map(fn (object $row): HeatmapCellData => new HeatmapCellData($row->weekday, $row->hour, $row->count))
            ->all();
    }

    /**
     * @param  Builder<Ticket>  $created
     * @return array<int, BreakdownData>
     */
    protected function bySource(Builder $created): array
    {
        return $created->toBase()
            ->selectRaw('source, count(*) as count')
            ->groupBy('source')
            ->orderByDesc('count')
            ->get()
            ->map(fn (object $row): BreakdownData => new BreakdownData($row->source, TicketSource::from($row->source)->label(), $row->count))
            ->all();
    }

    /**
     * @param  Builder<Ticket>  $created
     * @return array<int, BreakdownData>
     */
    protected function byDesk(Builder $created): array
    {
        $counts = $created->toBase()->selectRaw('desk_id, count(*) as count')->groupBy('desk_id')->orderByDesc('count')->pluck('count', 'desk_id');
        $names = Desk::withTrashed()->whereIn('id', $counts->keys())->pluck('name', 'id');

        return $counts->map(fn (int $count, string $deskId): BreakdownData => new BreakdownData($deskId, $names[$deskId], $count))->values()->all();
    }
}
