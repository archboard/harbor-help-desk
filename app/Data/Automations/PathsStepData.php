<?php

namespace App\Data\Automations;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * Runs the steps of the first branch whose condition passes, else the `else` steps.
 */
class PathsStepData extends StepData
{
    /**
     * @param  array<int, BranchData>  $branches
     * @param  array<int, StepData>  $else
     */
    public function __construct(
        public string $id,
        public array $branches = [],
        #[LiteralTypeScriptType('(App.Data.Automations.ConditionStepData | App.Data.Automations.ActionStepData | App.Data.Automations.DelayStepData | App.Data.Automations.PathsStepData | App.Data.Automations.StopStepData)[]')] public array $else = [],
        #[LiteralTypeScriptType("'paths'")] public string $type = 'paths',
    ) {}
}
