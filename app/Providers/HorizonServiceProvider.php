<?php

namespace App\Providers;

use App\Models\User;
use App\TenantFinders\SingleWorkspaceFinder;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     *
     * Horizon shows every tenant's jobs, so in multitenant mode only the platform operators on
     * the allowlist may see it. Self-hosted, the single workspace's owners and admins may.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (User $user): bool {
            if (config('harbor.multitenant')) {
                return $user->hasVerifiedEmail() && in_array(strtolower($user->email), config('harbor.horizon_emails'), true);
            }

            $workspace = app(SingleWorkspaceFinder::class)->findForRequest(request());

            return $workspace !== null && (bool) $user->workspaceRole($workspace)?->isAdmin();
        });
    }
}
