<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\ListAutomationRuns;
use App\Data\Automations\AutomationRunData;
use App\Data\Automations\ListAutomationRunsData;
use App\Http\Attributes\ApiQuery;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;

class AutomationRunController extends Controller
{
    /**
     * The automation's runs, or those of every automation the user may view, newest first.
     *
     * @return PaginatedDataCollection<int, AutomationRunData>
     */
    #[ApiQuery(ListAutomationRunsData::class)]
    public function index(Request $request, #[CurrentUser] User $user, ListAutomationRuns $listAutomationRuns, ?Automation $automation = null): PaginatedDataCollection
    {
        return AutomationRunData::collect($listAutomationRuns->handle($user, $automation, $request->query()), PaginatedDataCollection::class);
    }

    /**
     * One run with its definition snapshot and webhook payload.
     */
    public function show(AutomationRun $run): AutomationRunData
    {
        Gate::authorize('view', $run);

        return AutomationRunData::from($run->load(['automation', 'ticket']))->include('definition', 'payload');
    }
}
