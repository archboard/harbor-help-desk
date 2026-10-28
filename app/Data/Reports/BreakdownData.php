<?php

namespace App\Data\Reports;

use Spatie\LaravelData\Data;

/**
 * One slice of a count: a status, a source, a desk or a duration range.
 */
class BreakdownData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public int $count,
    ) {}
}
