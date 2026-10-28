<?php

namespace App\Data\Reports;

use Spatie\LaravelData\Data;

/**
 * What requesters said about their tickets. `score` is the percentage rated good, and
 * `responseRate` the percentage of surveys sent in the period that have been rated.
 */
class SatisfactionReportData extends Data
{
    /**
     * @param  array<int, SeriesPointData>  $trend  The `score` per bucket.
     * @param  array<int, SatisfactionDeskData>  $byDesk
     * @param  array<int, SatisfactionCommentData>  $comments  The latest 20, newest first.
     */
    public function __construct(
        public ReportPeriodData $period,
        public KpiData $good,
        public KpiData $bad,
        public KpiData $score,
        public KpiData $responseRate,
        public array $trend,
        public array $byDesk,
        public array $comments,
    ) {}
}
