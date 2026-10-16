<?php

namespace App\Policies;

use App\Enums\DeskRole;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;

/**
 * Workspace owners and admins manage every desk. Desk leads manage their own desk's
 * settings and members, and agents can only see the desks they're on.
 */
class DeskPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace) !== null;
    }

    public function view(User $user, Desk $desk): bool
    {
        return $this->isWorkspaceAdmin($user, $desk) || $user->deskRole($desk) !== null;
    }

    public function create(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace)?->isAdmin() === true;
    }

    public function update(User $user, Desk $desk): bool
    {
        return $this->isWorkspaceAdmin($user, $desk) || $user->deskRole($desk) === DeskRole::Lead;
    }

    public function manageMembers(User $user, Desk $desk): bool
    {
        return $this->update($user, $desk);
    }

    /**
     * Deleting a desk archives it.
     */
    public function delete(User $user, Desk $desk): bool
    {
        return $this->isWorkspaceAdmin($user, $desk);
    }

    public function restore(User $user, Desk $desk): bool
    {
        return $this->isWorkspaceAdmin($user, $desk);
    }

    protected function isWorkspaceAdmin(User $user, Desk $desk): bool
    {
        return $user->workspaceRole($desk->workspace)?->isAdmin() === true;
    }
}
