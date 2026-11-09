<?php

namespace Database\Factories;

use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketEvent>
 */
class TicketEventFactory extends Factory
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
            'type' => TicketEventType::CREATED,
            'properties' => [],
            'created_at' => now(),
        ];
    }
}
