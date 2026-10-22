<?php

namespace App\Automations;

use App\Enums\Concerns\HasLabels;

enum MatchMode: string
{
    use HasLabels;

    case ALL = 'all';
    case ANY = 'any';

    public function label(): string
    {
        return match ($this) {
            self::ALL => __('All of these'),
            self::ANY => __('Any of these'),
        };
    }
}
