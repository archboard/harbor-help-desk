<?php

namespace App\Data\Reports;

use Spatie\LaravelData\Data;

/**
 * How long tickets take to resolve, in minutes, and how often resolved tickets come back.
 * `reopenRate` is the percentage of tickets resolved in the period that were ever reopened.
 */
class ResolutionReportData extends Data
{
    /**
     * @param  array<int, BreakdownData>  $distribution
     * @param  array<int, SeriesPointData>  $trend  Median `resolution` minutes per bucket.
     */
    public function __construct(
        public ReportPeriodData $period,
        public KpiData $medianResolution,
        public KpiData $averageResolution,
        public KpiData $reopenRate,
        public array $distribution,
        public array $trend,
    ) {}
}
