<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;

class CatalogActionData extends Data
{
    /**
     * @param  string  $configType  The generated TypeScript type of the config, e.g. `App.Data.Automations.Actions.TagsConfigData`.
     * @param  array<string, list<string>>  $configRules  The string validation rules of each config key.
     * @param  array<string, list<CatalogOptionData>>  $configOptions  The labelled choices of each config key that has a fixed set.
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $configType,
        public array $configRules,
        public array $configOptions,
    ) {}
}
