<?php

namespace App\Policies;

use App\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;

/**
 * Workspace owners and admins manage the workspace's mailboxes, like its other settings.
 */
class MailboxPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace)?->isAdmin() === true;
    }

    public function view(User $user, Mailbox $mailbox): bool
    {
        return $this->update($user, $mailbox);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Mailbox $mailbox): bool
    {
        return $user->workspaceRole($mailbox->workspace)?->isAdmin() === true;
    }

    public function delete(User $user, Mailbox $mailbox): bool
    {
        return $this->update($user, $mailbox);
    }
}
