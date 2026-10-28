<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketMetric;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMetric>
 */
class TicketMetricFactory extends Factory
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
        ];
    }
}
