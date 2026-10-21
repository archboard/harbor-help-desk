<?php

namespace App\Data\BusinessSchedules;

use App\Data\InputData;
use Spatie\LaravelData\Optional;

class UpdateBusinessScheduleData extends InputData
{
    /**
     * @param  array<string, list<list<string>>>|Optional  $weeklyHours
     * @param  list<string>|Optional  $holidays
     */
    public function __construct(
        public string|Optional $name,
        public string|Optional $timezone,
        public array|Optional $weeklyHours,
        public array|Optional $holidays,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return CreateBusinessScheduleData::rulesWith('sometimes');
    }
}
