<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;

class TriggerData extends Data
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $type,
        public array $config = [],
    ) {}
}
