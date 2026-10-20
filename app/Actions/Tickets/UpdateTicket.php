<?php

namespace App\Actions\Tickets;

use App\Data\Tickets\UpdateTicketData;
use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\User;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateTicket
{
    /**
     * Change a ticket's fields, recording one event per field that actually changed.
     * Moving it to another desk is {@see MoveTicket}. `custom_fields` is patched: submitted
     * ids are set, or removed when null, and the rest are left alone.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Ticket $ticket, array $input): Ticket
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        $validated = UpdateTicketData::validateAndCreateWith($input, CustomFieldRules::for($ticket->desk, creating: false))->filled();

        $incoming = Arr::pull($validated, 'custom_fields', []);
        $ticket->fill($validated);

        DB::transaction(function () use ($actor, $ticket, $incoming): void {
            foreach ($this->changes($ticket) as [$type, $properties]) {
                $ticket->recordEvent($type, $actor, $properties);
            }

            $this->updateCustomFields($actor, $ticket, $incoming);

            $ticket->save();
        });

        return $ticket;
    }

    /**
     * Merge the submitted values into the ticket's, removing a field set to null, and record
     * an event for each value that changed.
     *
     * @param  array<string, mixed>  $incoming
     */
    protected function updateCustomFields(User $actor, Ticket $ticket, array $incoming): void
    {
        $old = $ticket->custom_fields;
        $new = array_diff_key($old, $incoming) + CustomFieldRules::cast($ticket->desk, $incoming);

        // ponytail: moving a ticket keeps values for fields the new desk hasn't enabled. They're
        // harmless; strip them in MoveTicket if that ever matters.
        $changed = false;

        foreach ($ticket->desk->customFields->whereIn('id', array_keys($incoming)) as $field) {
            if (($old[$field->id] ?? null) === ($new[$field->id] ?? null)) {
                continue;
            }

            $changed = true;
            $ticket->recordEvent(TicketEventType::CUSTOM_FIELD_CHANGED, $actor, [
                'field_id' => $field->id,
                'name' => $field->name,
                'from' => $old[$field->id] ?? null,
                'to' => $new[$field->id] ?? null,
            ]);
        }

        if ($changed) {
            $ticket->custom_fields = $new;
        }
    }

    /**
     * The event for each tracked field the update changes.
     *
     * @return array<int, array{TicketEventType, array{from: mixed, to: mixed}}>
     */
    protected function changes(Ticket $ticket): array
    {
        $events = [
            'subject' => fn (): array => [TicketEventType::SUBJECT_CHANGED, ['from' => $ticket->getOriginal('subject'), 'to' => $ticket->subject]],
            'status' => fn (): array => [TicketEventType::STATUS_CHANGED, ['from' => $ticket->getOriginal('status')->value, 'to' => $ticket->status->value]],
            'priority' => fn (): array => [TicketEventType::PRIORITY_CHANGED, ['from' => $ticket->getOriginal('priority')->value, 'to' => $ticket->priority->value]],
            'due_at' => fn (): array => [TicketEventType::DUE_DATE_CHANGED, ['from' => $ticket->getOriginal('due_at')?->toIso8601String(), 'to' => $ticket->due_at?->toIso8601String()]],
        ];

        return collect($events)
            ->filter(fn (Closure $event, string $attribute): bool => $ticket->isDirty($attribute))
            ->map(fn (Closure $event): array => $event())
            ->values()
            ->all();
    }
}
