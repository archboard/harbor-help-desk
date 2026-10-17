<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
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
            'desk_id' => Desk::factory(),
            'requester_id' => Contact::factory(),
            'subject' => fake()->sentence(),
        ];
    }
}
