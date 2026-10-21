<?php

namespace Database\Factories;

use App\Models\Desk;
use App\Models\Macro;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Workspace-wide by default.
 *
 * @extends Factory<Macro>
 */
class MacroFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => Workspace::current()?->getKey() ?? Workspace::factory(),
            'name' => fake()->words(2, true),
            'body' => fake()->sentence(),
            'body_is_internal' => false,
            'actions' => [],
        ];
    }

    public function personal(User $user): static
    {
        return $this->state(['user_id' => $user->getKey(), 'created_by' => $user->getKey()]);
    }

    public function forDesk(Desk $desk): static
    {
        return $this->state(['desk_id' => $desk->getKey()]);
    }
}
