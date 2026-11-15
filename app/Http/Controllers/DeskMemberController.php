<?php

namespace App\Http\Controllers;

use App\Actions\Desks\RemoveDeskMember;
use App\Actions\Desks\SetDeskMember;
use App\Data\Desks\DeskData;
use App\Data\Desks\DeskMemberData;
use App\Data\Users\UserData;
use App\Enums\DeskRole;
use App\Http\Flash;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeskMemberController extends Controller
{
    public function index(#[CurrentUser] User $user, Desk $desk): Response
    {
        Gate::authorize('view', $desk);

        $members = $desk->users()->orderBy('name')->get();
        $canManageMembers = $user->can('manageMembers', $desk);

        return Inertia::render('desks/Members', [
            'desk' => DeskData::from($desk),
            'members' => DeskMemberData::collect($members),
            'candidates' => UserData::collect($canManageMembers
                ? Workspace::current()->users()->whereKeyNot($members->modelKeys())->orderBy('name')->get()
                : []),
            'roles' => DeskRole::options(),
            'can' => ['manageMembers' => $canManageMembers],
        ]);
    }

    /**
     * Add the user to the desk, or change their role.
     */
    public function update(Request $request, #[CurrentUser] User $actor, Desk $desk, User $user, SetDeskMember $setDeskMember): RedirectResponse
    {
        $setDeskMember->handle($actor, $desk, $user, $request->all());

        Flash::success(__('Desk member saved.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $actor, Desk $desk, User $user, RemoveDeskMember $removeDeskMember): RedirectResponse
    {
        $removeDeskMember->handle($actor, $desk, $user);

        Flash::success(__('Desk member removed.'));

        return back();
    }
}
