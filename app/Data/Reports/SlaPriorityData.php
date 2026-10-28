<?php

namespace App\Data\Reports;

use App\Enums\TicketPriority;
use Spatie\LaravelData\Data;

/**
 * One metric's outcomes for tickets of one priority. `percentMet` is null with nothing decided yet.
 */
class SlaPriorityData extends Data
{
    public function __construct(
        public TicketPriority $priority,
        public int $met,
        public int $breached,
        public ?float $percentMet,
    ) {}
}
