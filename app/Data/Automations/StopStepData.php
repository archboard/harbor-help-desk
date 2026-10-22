<?php

namespace App\Data\Automations;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * Ends the run and skips the automations after it for this event.
 */
class StopStepData extends StepData
{
    public function __construct(
        public string $id,
        #[LiteralTypeScriptType("'stop'")] public string $type = 'stop',
    ) {}
}
