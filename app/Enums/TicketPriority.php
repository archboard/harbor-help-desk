<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum TicketPriority: string
{
    use HasLabels;

    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::LOW => __('Low'),
            self::NORMAL => __('Normal'),
            self::HIGH => __('High'),
            self::URGENT => __('Urgent'),
        };
    }
}
