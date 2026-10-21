<?php

namespace App\Actions\Tickets;

use App\Data\Tickets\CreateTicketData;
use App\Enums\TicketEventType;
use App\Enums\TicketSource;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateTicket
{
    public function __construct(
        protected SyncTicketAssignees $syncTicketAssignees,
        protected AddTicketComment $addTicketComment,
    ) {}

    /**
     * Open a ticket on an active desk. The requester is an existing contact (`requester_id`) or is
     * found or created by email (`requester.email`, `requester.name`). `custom_fields` maps the
     * desk's enabled field ids to values. An optional `body` (with `attachments`) becomes the
     * ticket's first reply, written by the actor. Contacts (opening a ticket by email) skip the
     * policy check, can't assign, and alone may set `mailbox_id` and the first reply's `message_id`.
     *
     * The `source` is where the caller received the ticket from, never user input.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User|Contact $actor, Desk $desk, array $input, TicketSource $source = TicketSource::WEB): Ticket
    {
        if ($actor instanceof User) {
            Gate::forUser($actor)->authorize('create', [Ticket::class, $desk]);
        }

        $validated = CreateTicketData::validateAndCreateWith($input, [
            'assignee_ids' => [$actor instanceof User ? 'sometimes' : 'prohibited'],
            'mailbox_id' => [$actor instanceof Contact ? 'sometimes' : 'prohibited'],
            'message_id' => [$actor instanceof Contact ? 'sometimes' : 'prohibited'],
            ...CustomFieldRules::for($desk, creating: true),
        ])->filled();

        return DB::transaction(function () use ($actor, $desk, $validated, $source): Ticket {
            $ticket = Ticket::create([
                ...Arr::only($validated, ['subject', 'status', 'priority', 'due_at', 'mailbox_id']),
                'source' => $source,
                'custom_fields' => CustomFieldRules::cast($desk, $validated['custom_fields'] ?? []),
                'desk_id' => $desk->getKey(),
                'requester_id' => $validated['requester_id'] ?? Contact::findOrCreateByEmail($validated['requester']['email'], $validated['requester']['name'])->getKey(),
            ]);

            $ticket->recordEvent(TicketEventType::CREATED, $actor);

            if ($actor instanceof User && isset($validated['assignee_ids'])) {
                $this->syncTicketAssignees->handle($actor, $ticket, ['user_ids' => $validated['assignee_ids']]);
            }

            if (filled($validated['body'] ?? null)) {
                $this->addTicketComment->handle($actor, $ticket, Arr::only($validated, ['body', 'attachments', 'message_id']));
            }

            return $ticket;
        });
    }
}
