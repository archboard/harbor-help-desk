<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;

class CatalogActionData extends Data
{
    /**
     * @param  array<string, list<string>>  $configRules  The string validation rules of each config key.
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $configRules,
    ) {}
}
