<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum Report: string
{
    use HasLabels;

    case OVERVIEW = 'overview';
    case RESPONSES = 'responses';
    case RESOLUTION = 'resolution';
    case AGENTS = 'agents';
    case SLA = 'sla';
    case SATISFACTION = 'satisfaction';

    public function label(): string
    {
        return match ($this) {
            self::OVERVIEW => __('Overview'),
            self::RESPONSES => __('Responses'),
            self::RESOLUTION => __('Resolution'),
            self::AGENTS => __('Agents'),
            self::SLA => __('SLA'),
            self::SATISFACTION => __('Satisfaction'),
        };
    }
}
