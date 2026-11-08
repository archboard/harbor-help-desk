<?php

namespace App\Data\Webhooks;

use App\Enums\TicketEventType;
use App\Models\Webhook;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Never carries the secret, see {@see WebhookSecretData}.
 */
class WebhookData extends Data
{
    /**
     * @param  list<TicketEventType>  $eventTypes  Empty means every event.
     */
    public function __construct(
        public string $id,
        public string $url,
        public ?string $description,
        public array $eventTypes,
        public bool $isEnabled,
        public int $consecutiveFailures,
        public ?CarbonImmutable $disabledAt,
        public ?string $createdBy,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromModel(Webhook $webhook): self
    {
        return new self(
            id: $webhook->id,
            url: $webhook->url,
            description: $webhook->description,
            eventTypes: array_values(array_map(TicketEventType::from(...), $webhook->event_types)),
            isEnabled: $webhook->is_enabled,
            consecutiveFailures: $webhook->consecutive_failures,
            disabledAt: $webhook->disabled_at,
            createdBy: $webhook->created_by,
            createdAt: $webhook->created_at,
            updatedAt: $webhook->updated_at,
        );
    }
}
