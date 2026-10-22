<?php

namespace App\Policies;

use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * A run is as visible as its automation: admins see all, a desk lead those of their desk's automations.
 */
class AutomationRunPolicy
{
    public function view(User $user, AutomationRun $run): bool
    {
        return Gate::forUser($user)->allows('view', $run->automation);
    }
}
