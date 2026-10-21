<?php

namespace App\Actions\BusinessSchedules;

use App\Models\BusinessSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteBusinessSchedule
{
    /**
     * ponytail: step 2 blocks this while an SLA policy uses the schedule.
     */
    public function handle(User $actor, BusinessSchedule $schedule): void
    {
        Gate::forUser($actor)->authorize('delete', $schedule);

        $schedule->delete();
    }
}
