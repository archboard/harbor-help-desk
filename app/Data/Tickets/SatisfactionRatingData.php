<?php

namespace App\Data\Tickets;

use App\Enums\SatisfactionScore;
use App\Models\SatisfactionRating;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class SatisfactionRatingData extends Data
{
    public function __construct(
        public string $id,
        public string $ticketId,
        public string $contactId,
        public SatisfactionScore $rating,
        public string $ratingLabel,
        public ?string $comment,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromModel(SatisfactionRating $rating): self
    {
        return new self(
            id: $rating->id,
            ticketId: $rating->ticket_id,
            contactId: $rating->contact_id,
            rating: $rating->rating,
            ratingLabel: $rating->rating->label(),
            comment: $rating->comment,
            createdAt: $rating->created_at,
            updatedAt: $rating->updated_at,
        );
    }
}
