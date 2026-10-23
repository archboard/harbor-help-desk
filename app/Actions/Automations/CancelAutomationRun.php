<?php

namespace App\Actions\Automations;

use App\Enums\AutomationRunStatus;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelAutomationRun
{
    /**
     * Cancel a run that is waiting on a delay, as someone who may edit its automation.
     */
    public function handle(User $actor, AutomationRun $run): AutomationRun
    {
        Gate::forUser($actor)->authorize('update', $run->automation);

        if ($run->status !== AutomationRunStatus::WAITING) {
            throw ValidationException::withMessages(['run' => __('Only a waiting run can be cancelled.')]);
        }

        $run->cancelWaiting(__('Cancelled by :name', ['name' => $actor->name]));

        return $run->refresh();
    }
}
