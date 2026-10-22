<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;

class CatalogPlaceholderData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}
}
