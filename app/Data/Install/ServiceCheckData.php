<?php

namespace App\Data\Install;

use Spatie\LaravelData\Data;

class ServiceCheckData extends Data
{
    public function __construct(
        public string $name,
        public bool $ok,
        public string $detail,
    ) {}
}
