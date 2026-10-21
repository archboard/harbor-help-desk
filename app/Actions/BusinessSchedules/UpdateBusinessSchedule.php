<?php

namespace App\Actions\BusinessSchedules;

use App\Data\BusinessSchedules\CreateBusinessScheduleData;
use App\Data\BusinessSchedules\UpdateBusinessScheduleData;
use App\Models\BusinessSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateBusinessSchedule
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, BusinessSchedule $schedule, array $input): BusinessSchedule
    {
        Gate::forUser($actor)->authorize('update', $schedule);

        $changes = UpdateBusinessScheduleData::validateAndCreate($input)->filled();

        if (isset($changes['weekly_hours'])) {
            $changes['weekly_hours'] = [...array_fill_keys(CreateBusinessScheduleData::DAYS, []), ...$changes['weekly_hours']];
        }

        if (isset($changes['holidays'])) {
            $changes['holidays'] = array_values(array_unique($changes['holidays']));
        }

        $schedule->update($changes);

        return $schedule;
    }
}
