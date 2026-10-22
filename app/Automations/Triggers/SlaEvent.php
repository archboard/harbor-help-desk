<?php

namespace App\Automations\Triggers;

use App\Automations\Context;
use App\Enums\SlaMetric;
use App\Enums\TicketEventType;
use App\Models\TicketEvent;
use Illuminate\Validation\Rule;

/**
 * An SLA clock is at risk (`warning`) or ran out (`breached`). `kind` picks one, both match when
 * it's absent. `metrics` limits it to first response and/or resolution, all when empty. The
 * event's `metric` property says which clock it was.
 */
class SlaEvent implements Trigger
{
    public function key(): string
    {
        return 'ticket.sla';
    }

    public function label(): string
    {
        return __('An SLA is at risk or breached');
    }

    public function configRules(): array
    {
        return [
            'kind' => ['sometimes', 'in:warning,breached'],
            'metrics' => ['sometimes', 'array'],
            'metrics.*' => ['string', Rule::enum(SlaMetric::class)],
        ];
    }

    public function fieldGroups(): array
    {
        return [];
    }

    public function matches(TicketEvent $event, array $config): bool
    {
        $kind = match ($event->type) {
            TicketEventType::SLA_WARNING => 'warning',
            TicketEventType::SLA_BREACHED => 'breached',
            default => null,
        };
        $metrics = $config['metrics'] ?? [];

        return $kind !== null
            && ($config['kind'] ?? $kind) === $kind
            && ($metrics === [] || in_array($event->properties['metric'] ?? null, $metrics, true));
    }

    public function placeholders(): array
    {
        return [];
    }

    public function placeholderValues(Context $context): array
    {
        return [];
    }
}
