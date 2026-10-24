<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum SatisfactionScore: string
{
    use HasLabels;

    case GOOD = 'good';
    case BAD = 'bad';

    public function label(): string
    {
        return match ($this) {
            self::GOOD => __('Good'),
            self::BAD => __('Bad'),
        };
    }
}
