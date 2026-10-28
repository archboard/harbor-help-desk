<?php

namespace App\Http\Controllers;

use App\Actions\Reports\BuildReport;
use App\Enums\Report;
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
        ]);
    }
}
