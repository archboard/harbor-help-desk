<?php

namespace App\Data\Automations;

use App\Automations\DelayUnit;
use Spatie\LaravelData\Data;

class DelayForData extends Data
{
    public function __construct(
        public int $amount,
        public DelayUnit $unit,
        public ?string $businessScheduleId = null,
    ) {}
}
