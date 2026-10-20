<?php

namespace App\Data\Tags;

use Spatie\LaravelData\Data;

class TagData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
