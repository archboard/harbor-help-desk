<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

/**
 * Workspace owners and admins manage the workspace's settings.
 */
class WorkspacePolicy
{
    public function update(User $user, Workspace $workspace): bool
    {
        return $user->workspaceRole($workspace)?->isAdmin() === true;
    }
}
