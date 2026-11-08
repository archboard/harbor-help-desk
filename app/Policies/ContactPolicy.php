<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use App\Models\Workspace;

/**
 * Contacts are shared across the workspace, so any member can see and edit them. Which of a
 * contact's tickets they see is still limited to their desks.
 */
class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace) !== null;
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->workspaceRole($contact->workspace) !== null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->view($user, $contact);
    }

    /**
     * Erasure is irreversible, so only workspace owners and admins may do it.
     */
    public function erase(User $user, Contact $contact): bool
    {
        return $user->workspaceRole($contact->workspace)?->isAdmin() === true;
    }
}
