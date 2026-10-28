<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum Report: string
{
    use HasLabels;

    case OVERVIEW = 'overview';
    case RESPONSES = 'responses';
    case RESOLUTION = 'resolution';

    public function label(): string
    {
        return match ($this) {
            self::OVERVIEW => __('Overview'),
            self::RESPONSES => __('Responses'),
            self::RESOLUTION => __('Resolution'),
        };
    }
}
