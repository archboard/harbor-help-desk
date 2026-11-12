<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ListWorkspaceInvitations
{
    /**
     * The current workspace's pending invitations, oldest first, with their desk and inviter.
     * Owners and admins only.
     *
     * @return Builder<WorkspaceInvitation>
     */
    public function handle(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('manageMembers', Workspace::current());

        return WorkspaceInvitation::query()->with(['desk', 'invitedBy'])->oldest()->orderBy('id');
    }
}
