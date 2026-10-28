<?php

namespace App\Data\Reports;

use Spatie\LaravelData\Data;

/**
 * Tickets created in one hour of one ISO weekday (1 = Monday), in the viewer's timezone.
 */
class HeatmapCellData extends Data
{
    public function __construct(
        public int $weekday,
        public int $hour,
        public int $count,
    ) {}
}
