<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Workspaces\ResendInvitation;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class ResendInvitationController extends Controller
{
    public function __invoke(#[CurrentUser] User $actor, WorkspaceInvitation $invitation, ResendInvitation $resendInvitation): RedirectResponse
    {
        $resendInvitation->handle($actor, $invitation);

        Flash::success(__('Invitation resent.'));

        return back();
    }
}
