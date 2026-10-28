<?php

namespace App\Data\Reports;

use Spatie\LaravelData\Data;

/**
 * How fast agents answer customers, in minutes. The `within` KPIs are percentages of first responses.
 */
class ResponsesReportData extends Data
{
    /**
     * @param  array<int, BreakdownData>  $firstResponseDistribution
     * @param  array<int, SeriesPointData>  $trend  Median `first_response` and `reply` minutes per bucket.
     */
    public function __construct(
        public ReportPeriodData $period,
        public KpiData $medianFirstResponse,
        public KpiData $averageFirstResponse,
        public KpiData $medianReply,
        public KpiData $averageReply,
        public KpiData $firstResponseWithinHour,
        public KpiData $firstResponseWithinFourHours,
        public KpiData $firstResponseWithinDay,
        public array $firstResponseDistribution,
        public array $trend,
    ) {}
}
