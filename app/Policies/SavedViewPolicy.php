<?php

namespace App\Policies;

use App\Models\SavedView;
use App\Models\User;
use App\Models\Workspace;

/**
 * Members see their own views and shared ones. Owners manage their views; workspace admins
 * can also tidy up shared ones. Only the owner decides whether a view is shared.
 */
class SavedViewPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace) !== null;
    }

    public function view(User $user, SavedView $savedView): bool
    {
        return $savedView->isOwnedBy($user)
            || ($savedView->is_shared && $user->workspaceRole($savedView->workspace) !== null);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SavedView $savedView): bool
    {
        return $savedView->isOwnedBy($user)
            || ($savedView->is_shared && $user->workspaceRole($savedView->workspace)?->isAdmin() === true);
    }

    public function delete(User $user, SavedView $savedView): bool
    {
        return $this->update($user, $savedView);
    }

    public function share(User $user, SavedView $savedView): bool
    {
        return $savedView->isOwnedBy($user);
    }
}
