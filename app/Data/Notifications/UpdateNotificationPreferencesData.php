<?php

namespace App\Data\Notifications;

use App\Data\InputData;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;

/**
 * Notification type → channel → whether the member wants it, e.g. `{"assigned": {"mail": false}}`.
 * Choices left out keep their current value.
 */
class UpdateNotificationPreferencesData extends InputData
{
    /**
     * @param  array<string, array<string, bool>>  $preferences
     */
    public function __construct(
        public array $preferences,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        $types = implode(',', array_column(NotificationType::cases(), 'value'));
        $channels = implode(',', array_column(NotificationChannel::cases(), 'value'));

        return [
            'preferences' => ['required', 'array:'.$types],
            'preferences.*' => ['array:'.$channels],
            'preferences.*.*' => ['boolean'],
        ];
    }
}
