<?php

namespace App\Listeners;

use App\Enums\WebhookDeliveryStatus;
use App\Events\TicketActivityRecorded;
use App\Jobs\SendTicketWebhook;
use App\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Create a delivery for every enabled webhook that subscribes to the recorded event and queue
 * it. Queued, and tenant-aware by the multitenancy default, so only the event's workspace's
 * webhooks match.
 */
class DispatchTicketWebhooks implements ShouldQueue
{
    public function handle(TicketActivityRecorded $event): void
    {
        $ticketEvent = $event->ticketEvent;

        Webhook::query()->where('is_enabled', true)->get()
            ->filter(fn (Webhook $webhook): bool => $webhook->subscribesTo($ticketEvent->type))
            ->each(function (Webhook $webhook) use ($ticketEvent): void {
                $delivery = $webhook->deliveries()->create([
                    'workspace_id' => $webhook->workspace_id,
                    'ticket_event_id' => $ticketEvent->getKey(),
                    'event_type' => $ticketEvent->type->value,
                    'status' => WebhookDeliveryStatus::PENDING,
                ]);

                SendTicketWebhook::dispatch($delivery);
            });
    }
}
