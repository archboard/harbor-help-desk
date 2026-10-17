<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketReminder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketReminder>
 */
class TicketReminderFactory extends Factory
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
            'user_id' => fn (array $attributes) => $attributes['created_by'],
            'created_by' => User::factory(),
            'remind_at' => now()->addDay(),
        ];
    }

    /**
     * A reminder for whoever is assigned to the ticket.
     */
    public function ticketLevel(): static
    {
        return $this->state(['user_id' => null]);
    }
}
