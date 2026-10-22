<?php

namespace App\Data\Automations;

use App\Automations\MatchMode;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

class ConditionGroupData extends ConditionNodeData
{
    /**
     * @param  array<int, ConditionNodeData>  $rules
     */
    public function __construct(
        public MatchMode $match,
        #[LiteralTypeScriptType('(App.Data.Automations.RuleData | App.Data.Automations.ConditionGroupData)[]')] public array $rules = [],
        #[LiteralTypeScriptType("'group'")] public string $type = 'group',
    ) {}
}
