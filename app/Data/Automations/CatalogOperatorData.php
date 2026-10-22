<?php

namespace App\Data\Automations;

use App\Automations\Operator;
use Spatie\LaravelData\Data;

class CatalogOperatorData extends Data
{
    public function __construct(
        public Operator $value,
        public string $label,
        public bool $takesValue,
    ) {}
}
