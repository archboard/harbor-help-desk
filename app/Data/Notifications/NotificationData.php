<?php

namespace App\Data\Notifications;

use App\Enums\NotificationType;
use App\Models\DatabaseNotification;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

class NotificationData extends Data
{
    /**
     * @param  array<string, mixed>  $data  The notification's payload. Ticket notifications carry `ticket_id`,
     *                                      `ticket_number` and `subject`, and may carry a `description`.
     */
    public function __construct(
        public string $id,
        public NotificationType $type,
        #[LiteralTypeScriptType('Record<string, unknown>')]
        public array $data,
        public ?CarbonImmutable $readAt,
        public ?CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(DatabaseNotification $notification): self
    {
        return new self(
            id: $notification->id,
            type: NotificationType::from($notification->type),
            data: $notification->data,
            readAt: $notification->read_at?->toImmutable(),
            createdAt: $notification->created_at?->toImmutable(),
        );
    }
}
