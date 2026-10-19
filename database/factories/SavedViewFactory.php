<?php

namespace Database\Factories;

use App\Models\SavedView;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedView>
 */
class SavedViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => Workspace::current()?->getKey() ?? Workspace::factory(),
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'filters' => ['status' => 'open'],
            'is_shared' => false,
        ];
    }

    public function shared(): static
    {
        return $this->state(['is_shared' => true]);
    }
}
