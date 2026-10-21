<?php

namespace Database\Factories;

use App\Models\SlaPolicy;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tracks the first response and resolution of urgent and normal tickets, on a 24/7 clock.
 *
 * @extends Factory<SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => Workspace::current()?->getKey() ?? Workspace::factory(),
            'business_schedule_id' => null,
            'name' => fake()->words(2, true),
            'warning_percent' => 75,
            'targets' => [
                'urgent' => ['first_response' => 60, 'resolution' => 480],
                'normal' => ['first_response' => 480, 'resolution' => 2880],
            ],
        ];
    }
}
