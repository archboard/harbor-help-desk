<?php

namespace App\Data\Reports;

use App\Enums\ReportBasis;
use Spatie\LaravelData\Data;

/**
 * The filters a report was built with, defaults filled in. `bucket` is `day` or `week`.
 */
class ReportPeriodData extends Data
{
    /**
     * @param  list<string>  $deskIds
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $previousFrom,
        public string $previousTo,
        public string $timezone,
        public string $bucket,
        public ReportBasis $basis,
        public array $deskIds,
    ) {}
}
