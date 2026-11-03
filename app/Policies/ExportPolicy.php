<?php

namespace App\Policies;

use App\Models\Export;
use App\Models\User;
use App\Models\Workspace;

/**
 * Only workspace owners and admins list exports and request them. Downloads also allow the
 * member who requested the export.
 */
class ExportPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function download(User $user, Export $export): bool
    {
        $workspace = Workspace::current();

        if ($workspace === null || $export->workspace_id !== $workspace->getKey() || $user->workspaceRole($workspace) === null) {
            return false;
        }

        return $this->isAdmin($user) || $export->requested_by === $user->getKey();
    }

    private function isAdmin(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace)?->isAdmin() === true;
    }
}
