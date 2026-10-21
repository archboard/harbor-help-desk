<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum SlaMetric: string
{
    use HasLabels;

    case FIRST_RESPONSE = 'first_response';
    case RESOLUTION = 'resolution';

    public function label(): string
    {
        return match ($this) {
            self::FIRST_RESPONSE => __('First response'),
            self::RESOLUTION => __('Resolution'),
        };
    }
}
