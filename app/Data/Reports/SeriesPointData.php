<?php

namespace App\Data\Reports;

use Spatie\LaravelData\Data;

/**
 * One day or week of a trend. `date` is the bucket's first day in the viewer's timezone.
 */
class SeriesPointData extends Data
{
    /**
     * @param  array<string, int|float|null>  $values
     */
    public function __construct(
        public string $date,
        public array $values,
    ) {}
}
