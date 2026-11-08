<?php

namespace App\Actions\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Gate;

class SendWebhookPing
{
    public function __construct(protected DeliverWebhook $deliverWebhook) {}

    /**
     * Send a `ping` right now, without retries, and return its delivery. It works on a disabled
     * webhook and doesn't count towards the failure limit.
     */
    public function handle(User $actor, Webhook $webhook): WebhookDelivery
    {
        Gate::forUser($actor)->authorize('update', $webhook);

        $delivery = $webhook->deliveries()->create([
            'workspace_id' => $webhook->workspace_id,
            'event_type' => WebhookDelivery::PING,
            'status' => WebhookDeliveryStatus::PENDING,
        ]);

        $this->deliverWebhook->handle($delivery, 1, true);

        return $delivery;
    }
}
