<?php

namespace App\Actions\Operations;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\TenantFinders\SingleWorkspaceFinder;

/**
 * Who runs the infrastructure: the platform operators on the `harbor.horizon_emails` allowlist
 * in the cloud, and the owners and admins of the single workspace when self-hosted.
 */
class OperatorRecipients
{
    /**
     * Whether the user may see the operator tools, such as the Horizon dashboard.
     */
    public function includes(User $user): bool
    {
        if (config('harbor.multitenant')) {
            return $user->hasVerifiedEmail() && in_array(strtolower($user->email), config('harbor.horizon_emails'), true);
        }

        $workspace = $this->workspace();

        return $workspace !== null && (bool) $user->workspaceRole($workspace)?->isAdmin();
    }

    /**
     * The addresses that alerts are mailed to.
     *
     * @return list<string>
     */
    public function emails(): array
    {
        if (config('harbor.multitenant')) {
            return config('harbor.horizon_emails');
        }

        return array_values($this->workspace()?->users()
            ->wherePivotIn('role', [WorkspaceRole::OWNER, WorkspaceRole::ADMIN])
            ->pluck('users.email')
            ->all() ?? []);
    }

    private function workspace(): ?Workspace
    {
        return app(SingleWorkspaceFinder::class)->findForRequest(request());
    }
}
