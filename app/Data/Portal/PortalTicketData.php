<?php

namespace App\Data\Portal;

use App\Data\Tickets\SatisfactionRatingData;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * What a contact sees of their own ticket: no custom fields, SLA, assignees or tags.
 */
class PortalTicketData extends Data
{
    public function __construct(
        public string $id,
        public int $number,
        public string $subject,
        public TicketStatus $status,
        public string $statusLabel,
        public string $deskName,
        public SatisfactionRatingData|Lazy|null $satisfactionRating,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromModel(Ticket $ticket): self
    {
        return new self(
            id: $ticket->id,
            number: $ticket->number,
            subject: $ticket->subject,
            status: $ticket->status,
            statusLabel: $ticket->status->label(),
            deskName: $ticket->desk->name,
            satisfactionRating: Lazy::whenLoaded('satisfactionRating', $ticket, fn (): ?SatisfactionRatingData => $ticket->satisfactionRating === null ? null : SatisfactionRatingData::from($ticket->satisfactionRating)),
            createdAt: $ticket->created_at,
            updatedAt: $ticket->updated_at,
        );
    }
}
