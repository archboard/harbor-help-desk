<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\CreateAutomation;
use App\Actions\Automations\DeleteAutomation;
use App\Actions\Automations\UpdateAutomation;
use App\Data\Automations\AutomationData;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class AutomationController extends Controller
{
    /**
     * The automations the user may manage, in the order they run.
     *
     * @return Collection<int, AutomationData>
     */
    public function index(Request $request): Collection
    {
        Gate::authorize('viewAny', Automation::class);

        return AutomationData::collect(Automation::visibleTo($request->user())->orderBy('trigger_type')->orderBy('position')->orderBy('id')->get());
    }

    public function store(Request $request, CreateAutomation $createAutomation): AutomationData
    {
        return AutomationData::from($createAutomation->handle($request->user(), $request->all()));
    }

    public function show(Automation $automation): AutomationData
    {
        Gate::authorize('view', $automation);

        return AutomationData::from($automation);
    }

    public function update(Request $request, Automation $automation, UpdateAutomation $updateAutomation): AutomationData
    {
        return AutomationData::from($updateAutomation->handle($request->user(), $automation, $request->all()));
    }

    public function destroy(Request $request, Automation $automation, DeleteAutomation $deleteAutomation): Response
    {
        $deleteAutomation->handle($request->user(), $automation);

        return response()->noContent();
    }
}
