<?php

namespace App\Policies;

use App\Enums\DeskRole;
use App\Models\Macro;
use App\Models\User;
use App\Models\Workspace;

/**
 * Personal macros belong to their owner. Workspace-wide macros are managed by workspace
 * admins, and a desk's macros by admins and that desk's leads. Shared macros are visible
 * to everyone who can see their desk. `create` is checked against the unsaved macro. Macros
 * are tenant-scoped, so they always belong to the current workspace.
 */
class MacroPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace) !== null;
    }

    public function view(User $user, Macro $macro): bool
    {
        if ($macro->user_id !== null) {
            return $macro->user_id === $user->getKey();
        }

        return $macro->desk_id === null
            ? $this->viewAny($user)
            : $this->isAdmin($user) || $user->deskRole($macro->desk) !== null;
    }

    public function create(User $user, Macro $macro): bool
    {
        return $this->update($user, $macro);
    }

    public function update(User $user, Macro $macro): bool
    {
        if ($macro->user_id !== null) {
            return $macro->user_id === $user->getKey();
        }

        return $this->isAdmin($user)
            || ($macro->desk_id !== null && $user->deskRole($macro->desk) === DeskRole::LEAD);
    }

    public function delete(User $user, Macro $macro): bool
    {
        return $this->update($user, $macro);
    }

    protected function isAdmin(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace)?->isAdmin() === true;
    }
}
