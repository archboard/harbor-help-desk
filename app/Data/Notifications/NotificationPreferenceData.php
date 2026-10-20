<?php

namespace App\Data\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\WorkspaceUser;
use Spatie\LaravelData\Data;

class NotificationPreferenceData extends Data
{
    /**
     * @param  array<string, bool>  $channels  Whether each channel is on, keyed by channel value.
     */
    public function __construct(
        public NotificationType $type,
        public string $label,
        public string $description,
        public array $channels,
    ) {}

    /**
     * One row per notification type with the member's effective choices.
     *
     * @return array<int, self>
     */
    public static function forMembership(WorkspaceUser $membership): array
    {
        return array_map(fn (NotificationType $type): self => new self(
            type: $type,
            label: $type->label(),
            description: $type->description(),
            channels: collect(NotificationChannel::cases())
                ->mapWithKeys(fn (NotificationChannel $channel): array => [$channel->value => $membership->wantsNotification($type, $channel)])
                ->all(),
        ), NotificationType::cases());
    }
}
