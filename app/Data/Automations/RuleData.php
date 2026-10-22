<?php

namespace App\Data\Automations;

use App\Automations\Operator;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * Compares one field with a value. A duration field's value is
 * `{amount, unit, business_schedule_id?}`; a field with a value-less operator has none.
 */
class RuleData extends ConditionNodeData
{
    public function __construct(
        public string $field,
        public Operator $operator,
        public mixed $value = null,
        #[LiteralTypeScriptType("'rule'")] public string $type = 'rule',
    ) {}
}
