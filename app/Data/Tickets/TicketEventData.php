<?php

namespace App\Data\Tickets;

use App\Enums\TicketEventType;
use App\Models\TicketEvent;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class TicketEventData extends Data
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public string $id,
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
