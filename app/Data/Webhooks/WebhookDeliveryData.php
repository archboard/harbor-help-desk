<?php

namespace App\Data\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class WebhookDeliveryData extends Data
{
    public function __construct(
        public string $id,
        public string $webhookId,
        public ?string $ticketEventId,
        public string $eventType,
        public WebhookDeliveryStatus $status,
        public ?int $responseStatus,
        public int $attempts,
        public ?string $error,
        public ?int $durationMs,
        public ?CarbonImmutable $deliveredAt,
        public ?CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(WebhookDelivery $delivery): self
    {
        return new self(
            id: $delivery->id,
            webhookId: $delivery->webhook_id,
            ticketEventId: $delivery->ticket_event_id,
            eventType: $delivery->event_type,
            status: $delivery->status,
            responseStatus: $delivery->response_status,
            attempts: $delivery->attempts,
            error: $delivery->error,
            durationMs: $delivery->duration_ms,
            deliveredAt: $delivery->delivered_at,
            createdAt: $delivery->created_at,
        );
    }
}
