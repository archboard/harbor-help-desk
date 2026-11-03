<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

/**
 * Only workspace owners and admins read the audit log, which covers both admin activity and
 * the workspace-wide ticket activity.
 */
class AuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace)?->isAdmin() === true;
    }
}
