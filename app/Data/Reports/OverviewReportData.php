<?php

namespace App\Data\Reports;

use Spatie\LaravelData\Data;

/**
 * Ticket volume: what came in, what got resolved or reopened, and what's open now.
 */
class OverviewReportData extends Data
{
    /**
     * @param  array<int, BreakdownData>  $backlog  Unresolved tickets now, by status.
     * @param  array<int, SeriesPointData>  $volume  `created` and `resolved` per bucket.
     * @param  array<int, HeatmapCellData>  $heatmap  Tickets created per weekday and hour, empty cells left out.
     * @param  array<int, BreakdownData>  $bySource
     * @param  array<int, BreakdownData>  $byDesk
     */
    public function __construct(
        public ReportPeriodData $period,
        public KpiData $created,
        public KpiData $resolved,
        public KpiData $reopened,
        public array $backlog,
        public array $volume,
        public array $heatmap,
        public array $bySource,
        public array $byDesk,
    ) {}
}
