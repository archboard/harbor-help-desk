<?php

namespace App\Http\Controllers;

use App\Actions\Automations\DeleteAutomation;
use App\Automations\AutomationRegistry;
use App\Data\Automations\AutomationData;
use App\Data\Desks\DeskData;
use App\Enums\AutomationRunStatus;
use App\Http\Flash;
use App\Models\Automation;
use App\Models\Desk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function index(Request $request, AutomationRegistry $registry): Response
    {
        Gate::authorize('viewAny', Automation::class);

        $user = $request->user();

        $automations = Automation::visibleTo($user)
            ->withRunStats()
            ->orderBy('trigger_type')->orderBy('position')->orderBy('id')
            ->get();

        return Inertia::render('automations/Index', [
            'automations' => AutomationData::collect($automations),
            'desks' => DeskData::collect(Desk::withTrashed()->whereIn('id', $automations->pluck('desk_id')->filter())->get()),
            'statuses' => AutomationRunStatus::options(),
            'triggers' => collect($registry->triggers())->map(fn ($trigger): string => $trigger->label())->all(),
        ]);
    }

    public function destroy(Request $request, Automation $automation, DeleteAutomation $deleteAutomation): RedirectResponse
    {
        $deleteAutomation->handle($request->user(), $automation);

        Flash::success(__('Automation deleted.'));

        return back();
    }
}
