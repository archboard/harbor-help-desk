<?php

namespace App\Data\Tickets;

use App\Models\TicketReminder;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class TicketReminderData extends Data
{
    public function __construct(
        public string $id,
        public string $ticketId,
        public ?string $userId,
        public bool $forAssignees,
        public ?string $createdBy,
        public CarbonImmutable $remindAt,
        public ?string $note,
        public ?CarbonImmutable $sentAt,
    ) {}

    public static function fromModel(TicketReminder $reminder): self
    {
        return new self(
            id: $reminder->id,
            ticketId: $reminder->ticket_id,
            userId: $reminder->user_id,
            forAssignees: $reminder->isTicketLevel(),
            createdBy: $reminder->created_by,
            remindAt: $reminder->remind_at,
            note: $reminder->note,
            sentAt: $reminder->sent_at,
        );
    }
}
