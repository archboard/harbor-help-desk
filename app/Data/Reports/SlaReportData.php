<?php

namespace App\Data\Reports;

use Spatie\LaravelData\Data;

/**
 * How well tickets met their SLA targets.
 */
class SlaReportData extends Data
{
    /**
     * @param  array<int, SlaMetricReportData>  $metrics
     * @param  array<int, SeriesPointData>  $breaches  Breaches per bucket, keyed by metric.
     */
    public function __construct(
        public ReportPeriodData $period,
        public array $metrics,
        public array $breaches,
    ) {}
}
