<?php

namespace App\Data\SlaPolicies;

use App\Data\InputData;
use App\Enums\SlaMetric;
use App\Enums\TicketPriority;
use Spatie\LaravelData\Optional;

/**
 * `targets` maps priorities to metrics in minutes, e.g. `{"urgent": {"first_response": 60}}`.
 * Any priority or metric may be left out. A null `business_schedule_id` means 24/7, and the
 * schedule must belong to the workspace, so `CreateSlaPolicy` checks that.
 */
class CreateSlaPolicyData extends InputData
{
    /**
     * @param  array<string, array<string, int>>  $targets
     */
    public function __construct(
        public string $name,
        public array $targets,
        public string|null|Optional $businessScheduleId,
        public int|Optional $warningPercent,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return self::rulesWith('required');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rulesWith(string $presence): array
    {
        $priorities = array_column(TicketPriority::cases(), 'value');
        $metrics = array_column(SlaMetric::cases(), 'value');

        return [
            'name' => [$presence, 'string', 'max:255'],
            'targets' => [$presence === 'required' ? 'present' : 'sometimes', 'array:'.implode(',', $priorities)],
            'targets.*' => ['array:'.implode(',', $metrics)],
            'targets.*.*' => ['integer', 'min:1', 'max:'.(60 * 24 * 365)],
            'business_schedule_id' => ['sometimes', 'nullable', 'string'],
            'warning_percent' => ['sometimes', 'integer', 'between:1,99'],
        ];
    }
}
