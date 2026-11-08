<?php

namespace Database\Factories;

use App\Models\Webhook;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An enabled webhook for every event type.
 *
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => Workspace::current()?->getKey() ?? Workspace::factory(),
            'url' => 'https://hooks.example.com/harbor',
            'description' => fake()->words(2, true),
            'event_types' => [],
            'secret' => 'whsec_'.Str::random(40),
            'is_enabled' => true,
            'consecutive_failures' => 0,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false, 'disabled_at' => now()]);
    }
}
