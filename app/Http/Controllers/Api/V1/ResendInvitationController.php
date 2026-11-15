<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\ResendInvitation;
use App\Data\Workspaces\WorkspaceInvitationData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Container\Attributes\CurrentUser;

class ResendInvitationController extends Controller
{
    /**
     * Email the invitation again with a fresh 7-day link.
     */
    #[ApiBody]
    public function __invoke(#[CurrentUser] User $actor, WorkspaceInvitation $invitation, ResendInvitation $resendInvitation): WorkspaceInvitationData
    {
        $resendInvitation->handle($actor, $invitation);

        return WorkspaceInvitationData::from($invitation->load(['desk', 'invitedBy']));
    }
}
