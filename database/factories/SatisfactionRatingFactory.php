<?php

namespace Database\Factories;

use App\Enums\SatisfactionScore;
use App\Models\SatisfactionRating;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SatisfactionRating>
 */
class SatisfactionRatingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => Workspace::current()?->getKey() ?? Workspace::factory(),
            'ticket_id' => Ticket::factory(),
            'contact_id' => fn (array $attributes) => Ticket::query()->whereKey($attributes['ticket_id'])->value('requester_id'),
            'rating' => SatisfactionScore::GOOD,
            'comment' => null,
        ];
    }
}
