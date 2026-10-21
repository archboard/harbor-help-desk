<?php

namespace Database\Factories;

use App\Models\BusinessSchedule;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Monday to Friday, 09:00 to 17:00 in UTC by default.
 *
 * @extends Factory<BusinessSchedule>
 */
class BusinessScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workday = [['09:00', '17:00']];

        return [
            'workspace_id' => fn () => Workspace::current()?->getKey() ?? Workspace::factory(),
            'name' => fake()->words(2, true),
            'timezone' => 'UTC',
            'weekly_hours' => [
                'mon' => $workday,
                'tue' => $workday,
                'wed' => $workday,
                'thu' => $workday,
                'fri' => $workday,
                'sat' => [],
                'sun' => [],
            ],
            'holidays' => [],
        ];
    }
}
