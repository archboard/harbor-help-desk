<?php

namespace App\Automations\Triggers;

use App\Automations\Context;
use App\Automations\FieldGroup;
use App\Models\TicketEvent;
use Illuminate\Support\Arr;

/**
 * A ticket field changed: status, priority, subject, due date, desk, SLA policy, a custom field,
 * a tag or an assignee. `fields` lists the field keys of {@see Context::change()} to react to,
 * and any change matches when it's empty.
 */
class FieldChanged implements Trigger
{
    public function key(): string
    {
        return 'ticket.field_changed';
    }

    public function label(): string
    {
        return __('A ticket field changes');
    }

    public function configRules(): array
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['string', 'regex:/^(ticket\.(subject|status|priority|due_at|sla_policy|desk|tags|assignees)|custom_field\..+)$/'],
        ];
    }

    public function fieldGroups(): array
    {
        return [FieldGroup::EVENT];
    }

    public function matches(TicketEvent $event, array $config): bool
    {
        $change = Context::changeFor($event);
        $fields = $config['fields'] ?? [];

        return $change !== null && ($fields === [] || in_array($change['field'], $fields, true));
    }

    public function placeholders(): array
    {
        return [
            'event.field' => __('Changed field'),
            'event.from' => __('Previous value'),
            'event.to' => __('New value'),
        ];
    }

    public function placeholderValues(Context $context): array
    {
        $change = $context->change();
        $text = fn (mixed $value): string => is_array($value) ? implode(', ', Arr::flatten($value)) : (string) ($value ?? '');

        return [
            'event.field' => $change['field'] ?? '',
            'event.from' => $text($change['from'] ?? null),
            'event.to' => $text($change['to'] ?? null),
        ];
    }
}
