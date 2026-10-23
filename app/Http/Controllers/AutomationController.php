<?php

namespace App\Http\Controllers;

use App\Actions\Automations\CreateAutomation;
use App\Actions\Automations\DeleteAutomation;
use App\Actions\Automations\UpdateAutomation;
use App\Automations\Actions\MoveDesk;
use App\Automations\AutomationRegistry;
use App\Automations\Catalog;
use App\Data\Automations\AutomationData;
use App\Data\BusinessSchedules\BusinessScheduleData;
use App\Data\Desks\DeskData;
use App\Data\Macros\MacroData;
use App\Data\SlaPolicies\SlaPolicyData;
use App\Enums\AutomationRunStatus;
use App\Enums\DeskRole;
use App\Http\Flash;
use App\Models\Automation;
use App\Models\BusinessSchedule;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    public function create(Request $request, Catalog $catalog): Response
    {
        Gate::authorize('viewAny', Automation::class);

        return $this->builder($request, $catalog);
    }

    public function store(Request $request, CreateAutomation $createAutomation): RedirectResponse
    {
        Gate::authorize('viewAny', Automation::class);

        $automation = $createAutomation->handle($request->user(), $request->all());

        Flash::success(__('Automation created.'));

        return to_route('automations.edit', $automation);
    }

    public function edit(Request $request, Automation $automation, Catalog $catalog): Response
    {
        Gate::authorize('update', $automation);

        return $this->builder($request, $catalog, $automation);
    }

    public function update(Request $request, Automation $automation, UpdateAutomation $updateAutomation): RedirectResponse
    {
        $updateAutomation->handle($request->user(), $automation, $request->all());

        Flash::success(__('Automation saved.'));

        return back();
    }

    public function destroy(Request $request, Automation $automation, DeleteAutomation $deleteAutomation): RedirectResponse
    {
        $deleteAutomation->handle($request->user(), $automation);

        Flash::success(__('Automation deleted.'));

        return back();
    }

    /**
     * The builder page. Admins may scope an automation to any active desk or the whole workspace,
     * a desk lead only to the desks they lead.
     */
    protected function builder(Request $request, Catalog $catalog, ?Automation $automation = null): Response
    {
        $user = $request->user();
        $isAdmin = $this->isAdmin($user);

        $desks = $isAdmin
            ? Desk::query()->where(fn (Builder $query) => $query->whereNull('archived_at')->orWhere('id', $automation?->desk_id))
            : $user->desks()->wherePivot('role', DeskRole::LEAD)->whereNull('archived_at');

        return Inertia::render('automations/Edit', [
            'automation' => $automation === null ? null : AutomationData::from($automation),
            'desks' => DeskData::collect($desks->orderBy('name')->get()),
            'canScopeToWorkspace' => $isAdmin,
            'schedules' => BusinessScheduleData::collect(BusinessSchedule::query()->orderBy('name')->get()),
            'moveDesks' => DeskData::collect($this->moveDesks($user, $automation)),
            'macros' => MacroData::collect(Macro::query()->whereNull('user_id')->visibleTo($user)->orderBy('name')->get()),
            'slaPolicies' => SlaPolicyData::collect(SlaPolicy::query()->orderBy('name')->get()),
            'catalog' => Inertia::defer(fn () => $catalog->build($user)),
        ]);
    }

    /**
     * The desks a move action may target: the active ones the user can open tickets on, the same check
     * as {@see MoveDesk::contextRules()}, plus any desk the automation already moves tickets to.
     *
     * @return Collection<int, Desk>
     */
    protected function moveDesks(User $user, ?Automation $automation): Collection
    {
        $targets = $automation === null ? [] : $this->moveTargets(json_decode(json_encode($automation->definition) ?: '[]', true));

        return Desk::withTrashed()
            ->where(fn (Builder $query) => $query->whereNull('archived_at')->visibleTo($user)->orWhereIn('id', $targets))
            ->orderBy('name')
            ->get()
            ->filter(fn (Desk $desk): bool => in_array($desk->getKey(), $targets, true) || Gate::forUser($user)->allows('create', [Ticket::class, $desk]))
            ->values();
    }

    /**
     * The desk ids of the move actions in a definition, nested paths included.
     *
     * @return list<string>
     */
    protected function moveTargets(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $found = ($node['action'] ?? null) === 'move_desk' && is_string($node['config']['desk_id'] ?? null) ? [$node['config']['desk_id']] : [];

        foreach ($node as $child) {
            $found = [...$found, ...$this->moveTargets($child)];
        }

        return $found;
    }

    protected function isAdmin(User $user): bool
    {
        return $user->workspaceRole(Workspace::current())?->isAdmin() === true;
    }
}
