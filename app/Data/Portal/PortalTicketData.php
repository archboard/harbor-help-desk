<?php

namespace App\Data\Portal;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

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
            createdAt: $ticket->created_at,
            updatedAt: $ticket->updated_at,
        );
    }
}
