<?php

namespace App\Actions\Tickets;

use App\Data\Tickets\SetTicketReminderData;
use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\TicketReminder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetTicketReminder
{
    /**
     * Set a personal reminder for the actor, or a ticket-level one for whoever is assigned
     * when `for_assignees` is true. Ticket-level reminders are logged; personal ones are not.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Ticket $ticket, array $input): TicketReminder
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        $validated = SetTicketReminderData::validateAndCreate($input)->filled();

        return DB::transaction(function () use ($actor, $ticket, $validated): TicketReminder {
            $reminder = $ticket->reminders()->create([
                'user_id' => ($validated['for_assignees'] ?? false) ? null : $actor->getKey(),
                'created_by' => $actor->getKey(),
                'remind_at' => $validated['remind_at'],
                'note' => $validated['note'] ?? null,
            ]);

            if ($reminder->isTicketLevel()) {
                $ticket->recordEvent(TicketEventType::REMINDER_SET, $actor, ['reminder_id' => $reminder->id, 'remind_at' => $reminder->remind_at->toIso8601String()]);
            }

            return $reminder;
        });
    }
}
