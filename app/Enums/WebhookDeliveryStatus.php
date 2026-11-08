<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum WebhookDeliveryStatus: string
{
    use HasLabels;

    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::SUCCEEDED => __('Succeeded'),
            self::FAILED => __('Failed'),
        };
    }
}
