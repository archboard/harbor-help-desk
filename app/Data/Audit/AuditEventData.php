<?php

namespace App\Data\Audit;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class AuditEventData extends Data
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public string $id,
        public AuditAction $action,
        public string $description,
        public string $actorName,
        public ?string $actorType,
        public ?string $actorId,
        public ?string $subjectType,
        public ?string $subjectId,
        public array $properties,
        public ?string $ip,
        public CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(AuditEvent $event): self
    {
        return new self(
            id: $event->id,
            action: $event->action,
            description: $event->action->label(),
            actorName: $event->actorName(),
            actorType: $event->actor_type,
            actorId: $event->actor_id,
            subjectType: $event->subject_type,
            subjectId: $event->subject_id,
            properties: $event->properties,
            ip: $event->ip,
            createdAt: $event->created_at,
        );
    }
}
