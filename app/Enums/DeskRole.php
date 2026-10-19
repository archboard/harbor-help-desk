<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum DeskRole: string
{
    use HasLabels;

    case LEAD = 'lead';
    case AGENT = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::LEAD => __('Lead'),
            self::AGENT => __('Agent'),
        };
    }
}
