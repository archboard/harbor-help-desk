<?php

namespace App\Data\Automations;

use App\Data\InputData;
use App\Enums\AutomationRunStatus;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;

/**
 * Filters of an automation's run list. `to` includes the whole day it names.
 */
class ListAutomationRunsData extends InputData
{
    public function __construct(
        public AutomationRunStatus|Optional $status,
        public string|Optional $ticketId,
        public string|Optional $from,
        public string|Optional $to,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(AutomationRunStatus::class)],
            'ticket_id' => ['sometimes', 'string'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }
}
