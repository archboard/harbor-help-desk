<?php

namespace App\Data\Tickets;

use App\Enums\SlaMetric;
use App\Models\TicketSlaTarget;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class TicketSlaTargetData extends Data
{
    public function __construct(
        public SlaMetric $metric,
        public string $metricLabel,
        public CarbonImmutable $startedAt,
        public ?CarbonImmutable $dueAt,
        public bool $paused,
        public ?CarbonImmutable $warnedAt,
        public ?CarbonImmutable $breachedAt,
        public ?CarbonImmutable $achievedAt,
    ) {}

    public static function fromModel(TicketSlaTarget $target): self
    {
        return new self(
            metric: $target->metric,
            metricLabel: $target->metric->label(),
            startedAt: $target->started_at,
            dueAt: $target->due_at,
            paused: $target->paused_at !== null,
            warnedAt: $target->warned_at,
            breachedAt: $target->breached_at,
            achievedAt: $target->achieved_at,
        );
    }
}
