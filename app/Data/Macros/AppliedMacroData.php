<?php

namespace App\Data\Macros;

use Spatie\LaravelData\Data;

class AppliedMacroData extends Data
{
    public function __construct(
        public ?string $body,
        public bool $isInternal,
    ) {}
}
