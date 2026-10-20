<?php

namespace App\Data\Notifications;

use App\Data\InputData;

/**
 * The notifications to mark read. Without `ids`, every unread notification in the workspace.
 */
class MarkNotificationsReadData extends InputData
{
    /**
     * @param  array<int, string>|null  $ids
     */
    public function __construct(
        public ?array $ids = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['string'],
        ];
    }
}
