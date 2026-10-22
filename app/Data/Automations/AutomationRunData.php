<?php

namespace App\Data\Automations;

use App\Enums\AutomationRunStatus;
use App\Models\AutomationRun;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * One evaluation of an automation. The list leaves out the heavy `definition` snapshot and the
 * webhook `payload`; the detail endpoint includes both. Only people who may view the automation get either.
 */
class AutomationRunData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $log
     * @param  array<string, mixed>|Lazy|null  $payload
     */
    public function __construct(
        public string $id,
        public string $automationId,
        public string|Lazy $automationName,
        public ?string $ticketId,
        public int|Lazy|null $ticketNumber,
        public string|Lazy|null $ticketSubject,
        public ?string $ticketEventId,
        public ?string $parentRunId,
        public int $depth,
        public AutomationRunStatus $status,
        public array $log,
        public ?string $error,
        public ?CarbonImmutable $resumeAt,
        public AutomationDefinitionData|Lazy $definition,
        public array|Lazy|null $payload,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromModel(AutomationRun $run): self
    {
        return new self(
            id: $run->id,
            automationId: $run->automation_id,
            automationName: Lazy::whenLoaded('automation', $run, fn (): string => $run->automation->name),
            ticketId: $run->ticket_id,
            ticketNumber: Lazy::whenLoaded('ticket', $run, fn (): ?int => $run->ticket?->number),
            ticketSubject: Lazy::whenLoaded('ticket', $run, fn (): ?string => $run->ticket?->subject),
            ticketEventId: $run->ticket_event_id,
            parentRunId: $run->parent_run_id,
            depth: $run->depth,
            status: $run->status,
            log: $run->log,
            error: $run->error,
            resumeAt: $run->resume_at,
            definition: Lazy::create(fn (): AutomationDefinitionData => $run->definition),
            payload: Lazy::create(fn (): ?array => $run->state['payload'] ?? null),
            createdAt: $run->created_at,
            updatedAt: $run->updated_at,
        );
    }
}
