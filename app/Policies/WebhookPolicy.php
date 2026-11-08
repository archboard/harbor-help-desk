<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Webhook;
use App\Models\Workspace;

/**
 * Webhooks reach outside the app and carry ticket data, so only workspace owners and admins
 * can see or manage them.
 */
class WebhookPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = Workspace::current();

        return $workspace !== null && $user->workspaceRole($workspace)?->isAdmin() === true;
    }

    public function view(User $user, Webhook $webhook): bool
    {
        return $user->workspaceRole($webhook->workspace)?->isAdmin() === true;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return $this->view($user, $webhook);
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $this->view($user, $webhook);
    }
}
