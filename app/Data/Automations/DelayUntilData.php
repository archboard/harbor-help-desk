<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;

/**
 * Wait until a ticket datetime field, shifted by `offset` minutes (negative is before it).
 */
class DelayUntilData extends Data
{
    public function __construct(
        public string $field,
        public int $offset = 0,
    ) {}
}
