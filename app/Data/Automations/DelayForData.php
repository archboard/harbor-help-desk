<?php

namespace App\Data\Automations;

use App\Automations\DelayUnit;
use App\Models\Workspace;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class DelayForData extends Data
{
    public function __construct(
        public int $amount,
        public DelayUnit $unit,
        public ?string $businessScheduleId = null,
    ) {}

    /**
     * Rules for an interval object submitted under `$key`.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rulesAt(string $key, int $minimum = 1): array
    {
        return [
            $key => ['required', 'array'],
            "$key.amount" => ['required', 'integer', "min:$minimum"],
            "$key.unit" => ['required', Rule::enum(DelayUnit::class)],
            "$key.business_schedule_id" => ['nullable', 'string', Rule::exists('business_schedules', 'id')->where('workspace_id', Workspace::current()?->getKey())],
        ];
    }
}
