<?php

namespace App\Data\Reports;

use App\Data\Users\UserData;
use Spatie\LaravelData\Data;

/**
 * One agent's work in the period. `openAssigned` is now, not in the period. Medians are minutes.
 */
class AgentRowData extends Data
{
    public function __construct(
        public UserData $agent,
        public int $publicReplies,
        public int $internalNotes,
        public int $ticketsSolved,
        public int $openAssigned,
        public ?float $medianReply,
        public ?float $medianFirstResponse,
    ) {}
}
