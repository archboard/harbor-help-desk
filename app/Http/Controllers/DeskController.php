<?php

namespace App\Http\Controllers;

use App\Actions\Desks\ArchiveDesk;
use App\Actions\Desks\CreateDesk;
use App\Actions\Desks\RestoreDesk;
use App\Actions\Desks\UpdateDesk;
use App\Data\CustomFields\CustomFieldData;
use App\Data\Desks\DeskData;
use App\Data\Desks\DeskMemberData;
use App\Data\SlaPolicies\SlaPolicyData;
use App\Data\Users\UserData;
use App\Enums\AssignmentStrategy;
use App\Enums\DeskRole;
use App\Http\Flash;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeskController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
    {
        Gate::authorize('viewAny', Desk::class);

        $canManageDesks = $user->can('create', Desk::class);

        return Inertia::render('desks/Index', [
            'desks' => DeskData::collect(Desk::visibleTo($user)->orderBy('name')->get()),
            'archivedDesks' => DeskData::collect($canManageDesks ? Desk::onlyTrashed()->orderBy('name')->get() : []),
            'canManageDesks' => $canManageDesks,
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user, CreateDesk $createDesk): RedirectResponse
    {
        $desk = $createDesk->handle($user, $request->all());

        Flash::success(__('Desk created.'));

        return to_route('desks.show', $desk);
    }

    public function show(#[CurrentUser] User $user, Desk $desk): Response
    {
        Gate::authorize('view', $desk);

        $members = $desk->users()->orderBy('name')->get();
        $canManageMembers = $user->can('manageMembers', $desk);

        return Inertia::render('desks/Show', [
            'desk' => DeskData::from($desk->load('customFields')),
            'members' => DeskMemberData::collect($members),
            'candidates' => UserData::collect($canManageMembers
                ? Workspace::current()->users()->whereKeyNot($members->modelKeys())->orderBy('name')->get()
                : []),
            'roles' => DeskRole::options(),
            'strategies' => AssignmentStrategy::options(),
            'customFields' => CustomFieldData::collect(CustomField::query()->orderBy('position')->orderBy('id')->get()),
            'slaPolicies' => SlaPolicyData::collect(SlaPolicy::query()->orderBy('name')->get()),
            'can' => [
                'update' => $user->can('update', $desk),
                'manageMembers' => $canManageMembers,
                'archive' => $user->can('delete', $desk),
            ],
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $user, Desk $desk, UpdateDesk $updateDesk): RedirectResponse
    {
        $updateDesk->handle($user, $desk, $request->all());

        Flash::success(__('Desk updated.'));

        return back();
    }

    /**
     * Archive the desk.
     */
    public function destroy(#[CurrentUser] User $user, Desk $desk, ArchiveDesk $archiveDesk): RedirectResponse
    {
        $archiveDesk->handle($user, $desk);

        Flash::success(__('Desk archived.'));

        return to_route('desks.index');
    }

    public function restore(#[CurrentUser] User $user, Desk $desk, RestoreDesk $restoreDesk): RedirectResponse
    {
        $restoreDesk->handle($user, $desk);

        Flash::success(__('Desk restored.'));

        return back();
    }
}
