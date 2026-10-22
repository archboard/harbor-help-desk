<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum AutomationRunStatus: string
{
    use HasLabels;

    case RUNNING = 'running';
    case WAITING = 'waiting';
    case COMPLETED = 'completed';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::RUNNING => __('Running'),
            self::WAITING => __('Waiting'),
            self::COMPLETED => __('Completed'),
            self::SKIPPED => __('Skipped'),
            self::FAILED => __('Failed'),
            self::CANCELLED => __('Cancelled'),
        };
    }
}
