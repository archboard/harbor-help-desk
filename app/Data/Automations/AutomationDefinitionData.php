<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * What an automation does: a trigger, then steps in order. Stored as the `definition` column.
 */
class AutomationDefinitionData extends Data
{
    /**
     * @param  array<int, StepData>  $steps
     */
    public function __construct(
        public TriggerData $trigger,
        #[LiteralTypeScriptType('(App.Data.Automations.ConditionStepData | App.Data.Automations.ActionStepData | App.Data.Automations.DelayStepData | App.Data.Automations.PathsStepData | App.Data.Automations.StopStepData)[]')] public array $steps = [],
    ) {}
}
