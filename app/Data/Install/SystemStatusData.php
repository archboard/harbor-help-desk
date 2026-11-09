<?php

namespace App\Data\Install;

use Spatie\LaravelData\Data;

class SystemStatusData extends Data
{
    /**
     * @param  list<ServiceCheckData>  $checks
     */
    public function __construct(
        public string $version,
        public array $checks,
    ) {}
}
