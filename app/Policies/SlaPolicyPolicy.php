<?php

namespace App\Policies;

use App\Models\SlaPolicy;
use App\Models\User;
use App\Models\Workspace;

/**
 * Any workspace member can see the SLA policies. Workspace owners and admins manage them.
 */
class SlaPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace) !== null;
    }

    public function view(User $user, SlaPolicy $slaPolicy): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace)?->isAdmin() === true;
    }

    public function update(User $user, SlaPolicy $slaPolicy): bool
    {
        return $user->workspaceRole($slaPolicy->workspace)?->isAdmin() === true;
    }

    public function delete(User $user, SlaPolicy $slaPolicy): bool
    {
        return $this->update($user, $slaPolicy);
    }
}
