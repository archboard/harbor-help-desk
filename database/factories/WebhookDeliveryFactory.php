<?php

namespace Database\Factories;

use App\Enums\WebhookDeliveryStatus;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending ping delivery.
 *
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => Workspace::current()?->getKey() ?? Workspace::factory(),
            'webhook_id' => Webhook::factory(),
            'ticket_event_id' => null,
            'event_type' => WebhookDelivery::PING,
            'status' => WebhookDeliveryStatus::PENDING,
            'attempts' => 0,
        ];
    }
}
