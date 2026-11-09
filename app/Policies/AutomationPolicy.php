<?php

namespace App\Policies;

use App\Enums\DeskRole;
use App\Models\Automation;
use App\Models\User;
use App\Models\Workspace;

/**
 * Workspace owners and admins manage every automation. A desk's lead manages the automations
 * limited to that desk. Agents can't see automations. `create` is checked against the unsaved
 * automation. Automations are tenant-scoped, so they always belong to the current workspace.
 */
class AutomationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->desks()->wherePivot('role', DeskRole::LEAD)->exists();
    }

    public function view(User $user, Automation $automation): bool
    {
        return $this->update($user, $automation);
    }

    public function create(User $user, Automation $automation): bool
    {
        return $this->update($user, $automation);
    }

    public function update(User $user, Automation $automation): bool
    {
        return $this->isAdmin($user)
            || ($automation->desk_id !== null && $user->deskRole($automation->loadMissing('desk')->desk) === DeskRole::LEAD);
    }

    public function delete(User $user, Automation $automation): bool
    {
        return $this->update($user, $automation);
    }

    protected function isAdmin(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace)?->isAdmin() === true;
    }
}
