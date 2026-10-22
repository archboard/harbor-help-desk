<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;

/**
 * What a test run found. `triggerMatched` is null when the trigger was not evaluated (see
 * `triggerNote`). `matched` is false when a condition stopped the steps, `stopped` when a stop
 * step was reached. Each `log` entry has the shape of a real run's log; an action's `result` is what it would do.
 */
class AutomationTestResultData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $log
     */
    public function __construct(
        public ?bool $triggerMatched,
        public ?string $triggerNote,
        public bool $matched,
        public bool $stopped,
        public array $log,
        public ?string $error,
    ) {}
}
