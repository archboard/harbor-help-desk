<?php

namespace App\Actions\BusinessSchedules;

use App\Data\BusinessSchedules\CreateBusinessScheduleData;
use App\Models\BusinessSchedule;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

class CreateBusinessSchedule
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): BusinessSchedule
    {
        Gate::forUser($actor)->authorize('create', BusinessSchedule::class);

        $data = CreateBusinessScheduleData::validateAndCreate($input);

        return BusinessSchedule::create([
            'workspace_id' => Workspace::current()?->getKey(),
            'name' => $data->name,
            'timezone' => $data->timezone,
            'weekly_hours' => [...array_fill_keys(CreateBusinessScheduleData::DAYS, []), ...$data->weeklyHours],
            'holidays' => $data->holidays instanceof Optional ? [] : array_values(array_unique($data->holidays)),
        ]);
    }
}
