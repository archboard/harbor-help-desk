<?php

namespace App\Data\Reports;

use Spatie\LaravelData\Data;

/**
 * Each agent's workload, by name.
 */
class AgentsReportData extends Data
{
    /**
     * @param  array<int, AgentRowData>  $agents
     */
    public function __construct(
        public ReportPeriodData $period,
        public array $agents,
    ) {}
}
