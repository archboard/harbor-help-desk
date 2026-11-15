<?php

namespace Database\Factories;

use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invitation to join as an agent, sent by a new user.
 *
 * @extends Factory<WorkspaceInvitation>
 */
class WorkspaceInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => Workspace::current()?->getKey() ?? Workspace::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => WorkspaceRole::AGENT,
            'invited_by_id' => User::factory(),
        ];
    }

    public function forDesk(Desk $desk): static
    {
        return $this->state(fn (): array => ['desk_id' => $desk->getKey()]);
    }
}
