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

    /**
     * Importing an export into the workspace is limited to admins as well.
     */
    public function import(User $user, Workspace $workspace): bool
    {
        return $this->update($user, $workspace);
    }
}
