<?php

namespace App\Data\Automations;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * Pauses the run for a duration (`for`) or until a ticket datetime (`until`). It's cancelled
 * when an event of a `cancelOn` trigger type happens on the ticket meanwhile.
 */
class DelayStepData extends StepData
{
    /**
     * @param  array<int, string>  $cancelOn
     */
    public function __construct(
        public string $id,
        public ?DelayForData $for = null,
        public ?DelayUntilData $until = null,
        public array $cancelOn = [],
        #[LiteralTypeScriptType("'delay'")] public string $type = 'delay',
    ) {}
}
