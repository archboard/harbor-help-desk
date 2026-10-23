<?php

namespace App\Data\Automations;

use App\Automations\FieldType;
use Spatie\LaravelData\Data;

class CatalogFieldData extends Data
{
    /**
     * @param  array<int, CatalogOptionData>  $options  The choices of an `enum`, `multi_enum`, `user`, `users` or `desk` field.
     * @param  bool  $settable  Whether the "Set a field" action can set it.
     */
    public function __construct(
        public string $key,
        public string $label,
        public FieldType $type,
        public array $options,
        public bool $settable,
    ) {}
}
