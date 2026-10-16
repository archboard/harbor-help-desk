<?php

namespace App\Http\Controllers;

use App\Actions\Desks\ArchiveDesk;
use App\Actions\Desks\CreateDesk;
use App\Actions\Desks\RestoreDesk;
use App\Actions\Desks\UpdateDesk;
use App\Http\Resources\DeskMemberResource;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeskController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Desk::class);

        $canManageDesks = $request->user()->can('create', Desk::class);

        return Inertia::render('desks/Index', [
            'desks' => Desk::visibleTo($request->user())->orderBy('name')->get(['id', 'name', 'slug']),
            'archivedDesks' => $canManageDesks ? Desk::onlyTrashed()->orderBy('name')->get(['id', 'name', 'slug']) : [],
            'canManageDesks' => $canManageDesks,
        ]);
    }

    public function store(Request $request, CreateDesk $createDesk): RedirectResponse
    {
        $desk = $createDesk->handle($request->user(), $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Desk created.')]);

        return to_route('desks.show', $desk);
    }

    public function show(Request $request, Desk $desk): Response
    {
        Gate::authorize('view', $desk);

        $members = $desk->users()->orderBy('name')->get();
        $canManageMembers = $request->user()->can('manageMembers', $desk);

        return Inertia::render('desks/Show', [
            'desk' => $desk->only(['id', 'name', 'slug']),
            'members' => DeskMemberResource::collection($members)->resolve($request),
            'candidates' => $canManageMembers
                ? Workspace::current()->users()->whereKeyNot($members->modelKeys())->orderBy('name')->get(['users.id', 'users.name'])
                    ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
                : [],
            'can' => [
                'update' => $request->user()->can('update', $desk),
                'manageMembers' => $canManageMembers,
                'archive' => $request->user()->can('delete', $desk),
            ],
        ]);
    }

    public function update(Request $request, Desk $desk, UpdateDesk $updateDesk): RedirectResponse
    {
        $updateDesk->handle($request->user(), $desk, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Desk updated.')]);

        return back();
    }

    /**
     * Archive the desk.
     */
    public function destroy(Request $request, Desk $desk, ArchiveDesk $archiveDesk): RedirectResponse
    {
        $archiveDesk->handle($request->user(), $desk);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Desk archived.')]);

        return to_route('desks.index');
    }

    public function restore(Request $request, Desk $desk, RestoreDesk $restoreDesk): RedirectResponse
    {
        $restoreDesk->handle($request->user(), $desk);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Desk restored.')]);

        return back();
    }
}
