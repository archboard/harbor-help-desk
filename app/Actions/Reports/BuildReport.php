<?php

namespace App\Actions\Reports;

use App\Data\Reports\OverviewReportData;
use App\Data\Reports\ResolutionReportData;
use App\Data\Reports\ResponsesReportData;
use App\Enums\Report;
use App\Models\User;

class BuildReport
{
    /**
     * Build one report for the viewer, counting only the desks they can see.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $viewer, Report $report, array $input): OverviewReportData|ResponsesReportData|ResolutionReportData
    {
        return match ($report) {
            Report::OVERVIEW => app(BuildOverviewReport::class)->handle($viewer, $input),
            Report::RESPONSES => app(BuildResponsesReport::class)->handle($viewer, $input),
            Report::RESOLUTION => app(BuildResolutionReport::class)->handle($viewer, $input),
        };
    }
}
