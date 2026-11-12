<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\InviteMember;
use App\Actions\Workspaces\ListWorkspaceInvitations;
use App\Actions\Workspaces\RevokeInvitation;
use App\Data\Workspaces\InviteMemberData;
use App\Data\Workspaces\WorkspaceInvitationData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\PaginatedDataCollection;

class InvitationController extends Controller
{
    /**
     * Pending invitations, oldest first. Owners and admins only.
     *
     * @return PaginatedDataCollection<int, WorkspaceInvitationData>
     */
    public function index(#[CurrentUser] User $actor, ListWorkspaceInvitations $listWorkspaceInvitations): PaginatedDataCollection
    {
        return WorkspaceInvitationData::collect($listWorkspaceInvitations->handle($actor)->paginate(), PaginatedDataCollection::class);
    }

    /**
     * Invite an email address and email them a link.
     */
    #[ApiBody(InviteMemberData::class)]
    public function store(Request $request, #[CurrentUser] User $actor, InviteMember $inviteMember): WorkspaceInvitationData
    {
        return WorkspaceInvitationData::from($inviteMember->handle($actor, $request->all())->load(['desk', 'invitedBy']));
    }

    public function destroy(#[CurrentUser] User $actor, WorkspaceInvitation $invitation, RevokeInvitation $revokeInvitation): Response
    {
        $revokeInvitation->handle($actor, $invitation);

        return response()->noContent();
    }
}
