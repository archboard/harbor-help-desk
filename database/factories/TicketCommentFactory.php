<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketComment>
 */
class TicketCommentFactory extends Factory
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
            'ticket_id' => Ticket::factory(),
            'author_type' => 'user',
            'author_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }

    public function internal(): static
    {
        return $this->state(['is_internal' => true]);
    }
}
