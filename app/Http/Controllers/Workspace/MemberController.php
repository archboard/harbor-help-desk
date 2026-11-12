<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Workspaces\RemoveMember;
use App\Actions\Workspaces\UpdateMemberRole;
use App\Data\Desks\DeskData;
use App\Data\Workspaces\WorkspaceInvitationData;
use App\Data\Workspaces\WorkspaceMemberData;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    /**
     * Like the rest of the workspace settings, the members page is for owners and admins.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        $workspace = Workspace::current();

        Gate::authorize('manageMembers', $workspace);

        $roles = $user->workspaceRole($workspace) === WorkspaceRole::OWNER
            ? WorkspaceRole::options()
            : array_diff_key(WorkspaceRole::options(), [WorkspaceRole::OWNER->value => true]);

        return Inertia::render('workspace/Members', [
            'members' => WorkspaceMemberData::collect($workspace->users()->withCount('desks')->orderBy('name')->get()),
            'invitations' => WorkspaceInvitationData::collect(WorkspaceInvitation::query()->with(['desk', 'invitedBy'])->oldest()->get()),
            'desks' => DeskData::collect(Desk::query()->orderBy('name')->get()),
            'roleLabels' => WorkspaceRole::options(),
            'roles' => $roles,
            'inviteRoles' => array_diff_key(WorkspaceRole::options(), [WorkspaceRole::OWNER->value => true]),
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $actor, User $member, UpdateMemberRole $updateMemberRole): RedirectResponse
    {
        $updateMemberRole->handle($actor, $member, $request->all());

        Flash::success(__('Role updated.'));

        return back();
    }

    /**
     * Removing yourself is leaving, which ends your session since you no longer belong here.
     */
    public function destroy(Request $request, #[CurrentUser] User $actor, User $member, RemoveMember $removeMember): RedirectResponse
    {
        $removeMember->handle($actor, $member);

        if ($actor->is($member)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        Flash::success(__('Member removed.'));

        return back();
    }
}
