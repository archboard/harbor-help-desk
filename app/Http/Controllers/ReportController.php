<?php

namespace App\Http\Controllers;

use App\Actions\Reports\BuildReport;
use App\Data\Desks\DeskData;
use App\Enums\Report;
use App\Enums\ReportBasis;
use App\Enums\SlaMetric;
use App\Enums\TicketPriority;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * One report over the desks the user can see, filtered by the query string.
     */
    public function show(Request $request, #[CurrentUser] User $user, Report $report, BuildReport $buildReport): Response
    {
        return Inertia::render('reports/Show', [
            'report' => $report,
            'reports' => Report::options(),
            'data' => $buildReport->handle($user, $report, $request->query()),
            'desks' => DeskData::collect(Desk::withTrashed()->visibleTo($user)->orderBy('name')->get()),
            'bases' => ReportBasis::options(),
            'slaMetrics' => SlaMetric::options(),
            'priorities' => TicketPriority::options(),
        ]);
    }
}
