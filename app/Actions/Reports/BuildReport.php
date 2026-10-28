<?php

namespace App\Actions\Reports;

use App\Enums\Report;
use App\Models\User;
use Spatie\LaravelData\Data;

class BuildReport
{
    /**
     * Build one report for the viewer, counting only the desks they can see.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $viewer, Report $report, array $input): Data
    {
        return match ($report) {
            Report::OVERVIEW => app(BuildOverviewReport::class)->handle($viewer, $input),
            Report::RESPONSES => app(BuildResponsesReport::class)->handle($viewer, $input),
            Report::RESOLUTION => app(BuildResolutionReport::class)->handle($viewer, $input),
            Report::AGENTS => app(BuildAgentsReport::class)->handle($viewer, $input),
            Report::SLA => app(BuildSlaReport::class)->handle($viewer, $input),
            Report::SATISFACTION => app(BuildSatisfactionReport::class)->handle($viewer, $input),
        };
    }
}
