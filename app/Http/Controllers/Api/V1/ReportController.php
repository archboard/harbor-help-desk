<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reports\BuildReport;
use App\Enums\Report;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

class ReportController extends Controller
{
    /**
     * One report over the desks the user can see, filtered by the query string.
     */
    public function show(Request $request, #[CurrentUser] User $user, Report $report, BuildReport $buildReport): Data
    {
        return $buildReport->handle($user, $report, $request->query());
    }
}
