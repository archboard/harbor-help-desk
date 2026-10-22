<?php

namespace App\Data\Automations;

use App\Automations\MatchMode;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * Stops the run when its rules don't pass.
 */
class ConditionStepData extends StepData
{
    /**
     * @param  array<int, ConditionNodeData>  $rules
     */
    public function __construct(
        public string $id,
        public MatchMode $match,
        #[LiteralTypeScriptType('(App.Data.Automations.RuleData | App.Data.Automations.ConditionGroupData)[]')] public array $rules = [],
        #[LiteralTypeScriptType("'condition'")] public string $type = 'condition',
    ) {}
}
