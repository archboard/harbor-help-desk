<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reports\BuildReport;
use App\Data\Reports\OverviewReportData;
use App\Data\Reports\ResolutionReportData;
use App\Data\Reports\ResponsesReportData;
use App\Enums\Report;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * One report over the desks the user can see, filtered by the query string.
     */
    public function show(Request $request, #[CurrentUser] User $user, Report $report, BuildReport $buildReport): OverviewReportData|ResponsesReportData|ResolutionReportData
    {
        return $buildReport->handle($user, $report, $request->query());
    }
}
