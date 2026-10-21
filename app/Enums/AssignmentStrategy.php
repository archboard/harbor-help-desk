<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum AssignmentStrategy: string
{
    use HasLabels;

    case MANUAL = 'manual';
    case ROUND_ROBIN = 'round_robin';
    case LOAD_BALANCED = 'load_balanced';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => __('Manual'),
            self::ROUND_ROBIN => __('Round robin'),
            self::LOAD_BALANCED => __('Load balanced'),
        };
    }
}
