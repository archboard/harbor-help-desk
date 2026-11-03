<?php

namespace App\Data\Tickets;

use App\Enums\TicketEventType;
use App\Models\TicketEvent;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class TicketEventData extends Data
{
    /**
     * The ticket's id and number are only present when the event was loaded with its ticket, as in the workspace-wide list.
     *
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public string $id,
        public string|Optional $ticketId,
        public int|Optional $ticketNumber,
        public TicketEventType $type,
        public string $description,
        public string $actorName,
        public ?string $actorType,
        public ?string $actorId,
        public ?string $automationRunId,
        public array $properties,
        public CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(TicketEvent $event): self
    {
        return new self(
            id: $event->id,
            ticketId: $event->relationLoaded('ticket') ? $event->ticket_id : Optional::create(),
            ticketNumber: $event->relationLoaded('ticket') && $event->ticket !== null ? $event->ticket->number : Optional::create(),
            type: $event->type,
            description: $event->description(),
            actorName: $event->actorName(),
            actorType: $event->actor_type,
            actorId: $event->actor_id,
            automationRunId: $event->automation_run_id,
            properties: $event->properties,
            createdAt: $event->created_at,
        );
    }
}
