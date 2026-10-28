<?php

namespace App\Data\Reports;

use Spatie\LaravelData\Data;

/**
 * One number for the period, with the same number for the period of equal length before it.
 */
class KpiData extends Data
{
    public function __construct(
        public int|float|null $value,
        public int|float|null $previous,
    ) {}
}
