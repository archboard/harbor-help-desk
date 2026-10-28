<?php

namespace App\Data\Reports;

use App\Enums\SatisfactionScore;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * A rating the requester left a comment with.
 */
class SatisfactionCommentData extends Data
{
    public function __construct(
        public string $ticketId,
        public int $ticketNumber,
        public SatisfactionScore $rating,
        public string $comment,
        public ?CarbonImmutable $createdAt,
    ) {}
}
