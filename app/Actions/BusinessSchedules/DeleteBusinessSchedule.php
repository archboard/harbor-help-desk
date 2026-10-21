<?php

namespace App\Actions\BusinessSchedules;

use App\Models\BusinessSchedule;
use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteBusinessSchedule
{
    /**
     * A schedule can't be deleted while an SLA policy measures its targets in it.
     */
    public function handle(User $actor, BusinessSchedule $schedule): void
    {
        Gate::forUser($actor)->authorize('delete', $schedule);

        if (SlaPolicy::query()->where('business_schedule_id', $schedule->getKey())->exists()) {
            throw ValidationException::withMessages(['business_schedule' => __('This schedule is used by an SLA policy. Change or delete the policy first.')]);
        }

        $schedule->delete();
    }
}
