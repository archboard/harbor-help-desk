<?php

namespace App\Data\Automations;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

class ActionStepData extends StepData
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $id,
        public string $action,
        public array $config = [],
        #[LiteralTypeScriptType("'action'")] public string $type = 'action',
    ) {}
}
