<?php

namespace App\Data\Reports;

use App\Enums\SlaMetric;
use Spatie\LaravelData\Data;

/**
 * The outcomes of one SLA metric's targets on tickets created in the period. `percentMet` leaves
 * out targets still in progress.
 */
class SlaMetricReportData extends Data
{
    /**
     * @param  array<int, SlaPriorityData>  $byPriority
     */
    public function __construct(
        public SlaMetric $metric,
        public int $met,
        public int $breached,
        public int $inProgress,
        public KpiData $percentMet,
        public array $byPriority,
    ) {}
}
