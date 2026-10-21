<?php

namespace App\Policies;

use App\Models\BusinessSchedule;
use App\Models\User;
use App\Models\Workspace;

/**
 * Any workspace member can see the schedules. Workspace owners and admins manage them.
 */
class BusinessSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace) !== null;
    }

    public function view(User $user, BusinessSchedule $schedule): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace)?->isAdmin() === true;
    }

    public function update(User $user, BusinessSchedule $schedule): bool
    {
        return $user->workspaceRole($schedule->workspace)?->isAdmin() === true;
    }

    public function delete(User $user, BusinessSchedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }
}
