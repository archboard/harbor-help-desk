<?php

namespace App\Automations;

use App\Enums\Concerns\HasLabels;

enum DelayUnit: string
{
    use HasLabels;

    case MINUTES = 'minutes';
    case HOURS = 'hours';
    case DAYS = 'days';

    public function label(): string
    {
        return match ($this) {
            self::MINUTES => __('Minutes'),
            self::HOURS => __('Hours'),
            self::DAYS => __('Days'),
        };
    }

    /**
     * ponytail: a day is 1440 minutes, also in business time (not the schedule's open hours per day).
     */
    public function toMinutes(int $amount): int
    {
        return $amount * match ($this) {
            self::MINUTES => 1,
            self::HOURS => 60,
            self::DAYS => 1440,
        };
    }
}
