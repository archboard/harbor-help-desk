<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum TicketStatus: string
{
    use HasLabels;

    case OPEN = 'open';
    case PENDING = 'pending';
    case ON_HOLD = 'on_hold';
    case SOLVED = 'solved';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => __('Open'),
            self::PENDING => __('Pending'),
            self::ON_HOLD => __('On hold'),
            self::SOLVED => __('Solved'),
            self::CLOSED => __('Closed'),
        };
    }
}
