<?php

namespace App\Data\BusinessSchedules;

use App\Models\BusinessSchedule;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class BusinessScheduleData extends Data
{
    /**
     * @param  array<string, list<list<string>>>  $weeklyHours
     * @param  list<string>  $holidays
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $timezone,
        public array $weeklyHours,
        public array $holidays,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromModel(BusinessSchedule $schedule): self
    {
        return new self(
            id: $schedule->id,
            name: $schedule->name,
            timezone: $schedule->timezone,
            weeklyHours: $schedule->weekly_hours,
            holidays: array_values($schedule->holidays),
            createdAt: $schedule->created_at,
            updatedAt: $schedule->updated_at,
        );
    }
}
