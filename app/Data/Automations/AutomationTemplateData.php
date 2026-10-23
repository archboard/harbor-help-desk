<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;

/**
 * A ready-made automation the builder can start from. Values that depend on the workspace
 * (a desk, a keyword, a URL) hold editable examples or stay empty.
 */
class AutomationTemplateData extends Data
{
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public string $category,
        public string $triggerType,
        public AutomationDefinitionData $definition,
    ) {}
}
