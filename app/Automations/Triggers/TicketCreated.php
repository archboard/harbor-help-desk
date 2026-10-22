<?php

namespace App\Automations\Triggers;

use App\Automations\Context;
use App\Enums\TicketEventType;
use App\Enums\TicketSource;
use App\Models\TicketEvent;
use Illuminate\Validation\Rule;

/**
 * A ticket was created. Without `sources` every source matches.
 */
class TicketCreated implements Trigger
{
    public function key(): string
    {
        return 'ticket.created';
    }

    public function label(): string
    {
        return __('A ticket is created');
    }

    public function configRules(): array
    {
        return [
            'sources' => ['sometimes', 'array'],
            'sources.*' => ['string', Rule::enum(TicketSource::class)],
        ];
    }

    public function fieldGroups(): array
    {
        return [];
    }

    public function matches(TicketEvent $event, array $config): bool
    {
        $sources = $config['sources'] ?? [];

        return $event->type === TicketEventType::CREATED
            && ($sources === [] || in_array($event->ticket->source->value, $sources, true));
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
