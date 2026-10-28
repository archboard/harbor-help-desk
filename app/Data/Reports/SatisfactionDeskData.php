<?php

namespace App\Data\Reports;

use Spatie\LaravelData\Data;

/**
 * One desk's ratings in the period. `score` is the percentage rated good.
 */
class SatisfactionDeskData extends Data
{
    public function __construct(
        public string $deskId,
        public string $name,
        public int $good,
        public int $bad,
        public float $score,
    ) {}
}
