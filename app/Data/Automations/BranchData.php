<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

class BranchData extends Data
{
    /**
     * @param  array<int, StepData>  $steps
     */
    public function __construct(
        public string $name,
        public ConditionGroupData $condition,
        #[LiteralTypeScriptType('(App.Data.Automations.ConditionStepData | App.Data.Automations.ActionStepData | App.Data.Automations.DelayStepData | App.Data.Automations.PathsStepData | App.Data.Automations.StopStepData)[]')] public array $steps = [],
    ) {}
}
