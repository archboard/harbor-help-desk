<?php

namespace App\Http\Controllers;

use App\Actions\Automations\ListAutomationRuns;
use App\Automations\Catalog;
use App\Data\Automations\AutomationData;
use App\Data\Automations\AutomationRunData;
use App\Data\BusinessSchedules\BusinessScheduleData;
use App\Data\Desks\DeskData;
use App\Data\Macros\MacroData;
use App\Data\SlaPolicies\SlaPolicyData;
use App\Data\Tickets\TicketEventData;
use App\Enums\AutomationRunStatus;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\BusinessSchedule;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\SlaPolicy;
use App\Models\TicketEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AutomationRunController extends Controller
{
    /**
     * The runs of one automation, or of every automation the user may view, newest first.
     */
    public function index(Request $request, ListAutomationRuns $listAutomationRuns, ?Automation $automation = null): Response
    {
        $runs = $listAutomationRuns->handle($request->user(), $automation, $request->query());

        return Inertia::render('automations/Runs', [
            'automation' => $automation === null ? null : AutomationData::from($automation),
            'runs' => Inertia::scroll(AutomationRunData::collect($runs)),
            'filters' => (object) $request->only('status', 'ticket_number'),
            'statuses' => AutomationRunStatus::options(),
        ]);
    }

    /**
     * One run: its definition snapshot drawn read-only with the log over it. The lookups
     * only name what the snapshot points at.
     */
    public function show(Request $request, AutomationRun $run, Catalog $catalog): Response
    {
        Gate::authorize('view', $run);

        $user = $request->user();
        $event = $run->ticket_event_id === null ? null : TicketEvent::query()->with('ticket')->find($run->ticket_event_id);
        $desks = DeskData::collect(Desk::withTrashed()->orderBy('name')->get());

        return Inertia::render('automations/Run', [
            'run' => AutomationRunData::from($run->load(['automation', 'ticket']))->include('definition', 'payload'),
            'event' => $event !== null && $user->can('view', $event->ticket) ? TicketEventData::from($event) : null,
            'canCancel' => $user->can('update', $run->automation),
            'statuses' => AutomationRunStatus::options(),
            'desks' => $desks,
            'schedules' => BusinessScheduleData::collect(BusinessSchedule::query()->orderBy('name')->get()),
            'macros' => MacroData::collect(Macro::query()->whereNull('user_id')->visibleTo($user)->orderBy('name')->get()),
            'slaPolicies' => SlaPolicyData::collect(SlaPolicy::query()->orderBy('name')->get()),
            'catalog' => Inertia::defer(fn () => $catalog->build($user)),
        ]);
    }
}
