<?php

namespace App\Data\Automations;

use App\Enums\AutomationRunStatus;
use App\Models\AutomationRun;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Just enough of a run to show its outcome in a list, without the log, snapshot or payload.
 */
class AutomationRunSummaryData extends Data
{
    public function __construct(
        public string $id,
        public AutomationRunStatus $status,
        public ?CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(AutomationRun $run): self
    {
        return new self(id: $run->id, status: $run->status, createdAt: $run->created_at);
    }
}
