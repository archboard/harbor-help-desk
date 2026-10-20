<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

/**
 * Where a notification is delivered. Values are Laravel's channel names, so `via()` returns them as they are.
 */
enum NotificationChannel: string
{
    use HasLabels;

    case DATABASE = 'database';
    case MAIL = 'mail';

    public function label(): string
    {
        return match ($this) {
            self::DATABASE => __('In-app'),
            self::MAIL => __('Email'),
        };
    }
}
