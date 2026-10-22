<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;

class CatalogOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
