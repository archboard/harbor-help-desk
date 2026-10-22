<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;

/**
 * Everything the automation builder renders from, for one viewer.
 */
class AutomationCatalogData extends Data
{
    /**
     * @param  list<CatalogTriggerData>  $triggers
     * @param  list<CatalogFieldGroupData>  $fieldGroups
     * @param  array<string, array<int, CatalogOperatorData>>  $operators  Keyed by field type.
     * @param  list<CatalogActionData>  $actions
     * @param  list<CatalogPlaceholderData>  $placeholders
     */
    public function __construct(
        public array $triggers,
        public array $fieldGroups,
        public array $operators,
        public array $actions,
        public array $placeholders,
    ) {}
}
