<?php

namespace App\Data\Automations;

use App\Automations\FieldGroup;
use Spatie\LaravelData\Data;

class CatalogFieldGroupData extends Data
{
    /**
     * @param  array<int, CatalogFieldData>  $fields
     */
    public function __construct(
        public FieldGroup $group,
        public string $label,
        public array $fields,
    ) {}
}
