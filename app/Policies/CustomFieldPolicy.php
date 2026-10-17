<?php

namespace App\Policies;

use App\Models\CustomField;
use App\Models\User;
use App\Models\Workspace;

/**
 * Any workspace member can see the fields. Workspace owners and admins define them.
 * Enabling fields on a desk is {@see DeskPolicy::update()}.
 */
class CustomFieldPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace) !== null;
    }

    public function create(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace)?->isAdmin() === true;
    }

    public function update(User $user, CustomField $customField): bool
    {
        return $user->workspaceRole($customField->workspace)?->isAdmin() === true;
    }

    public function delete(User $user, CustomField $customField): bool
    {
        return $this->update($user, $customField);
    }
}
