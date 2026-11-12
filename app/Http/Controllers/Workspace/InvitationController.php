<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Workspaces\InviteMember;
use App\Actions\Workspaces\RevokeInvitation;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function store(Request $request, #[CurrentUser] User $actor, InviteMember $inviteMember): RedirectResponse
    {
        $inviteMember->handle($actor, $request->all());

        Flash::success(__('Invitation sent.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $actor, WorkspaceInvitation $invitation, RevokeInvitation $revokeInvitation): RedirectResponse
    {
        $revokeInvitation->handle($actor, $invitation);

        Flash::success(__('Invitation revoked.'));

        return back();
    }
}
