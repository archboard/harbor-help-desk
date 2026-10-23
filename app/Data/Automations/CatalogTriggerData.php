<?php

namespace App\Data\Automations;

use App\Automations\FieldGroup;
use Spatie\LaravelData\Data;

class CatalogTriggerData extends Data
{
    /**
     * @param  list<FieldGroup>  $fieldGroups  The field groups rules of an automation with this trigger can use.
     * @param  array<string, list<string>>  $configRules  The string validation rules of each config key.
     * @param  array<string, list<CatalogOptionData>>  $configOptions  The labelled choices of each config key that has a fixed set.
     * @param  list<CatalogPlaceholderData>  $placeholders  The placeholders only this trigger fills.
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $fieldGroups,
        public array $configRules,
        public array $configOptions,
        public array $placeholders = [],
    ) {}
}
