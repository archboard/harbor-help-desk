<?php

namespace App\Actions\Tickets;

use App\Data\Tickets\CreateTicketData;
use App\Enums\TicketEventType;
use App\Enums\TicketSource;
use App\Models\Automation;
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
        protected AddTicketAttachment $addTicketAttachment,
    ) {}

    /**
     * Open a ticket on an active desk. The requester is an existing contact (`requester_id`) or is
     * found or created by email (`requester.email`, `requester.name`). `custom_fields` maps the
     * desk's enabled field ids to values. An optional `body` (with `attachments`) becomes the
     * ticket's opening message, written by the actor; it is part of the `created` event (its `comment_id`), not a reply. An automation may assign. Contacts (opening a ticket by email or the portal) skip
     * the policy check, can't assign or set status, priority, due date or custom fields (so required fields don't apply), and alone may set `mailbox_id` and the first reply's `message_id`.
     *
     * The `source` is where the caller received the ticket from, never user input.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User|Contact|Automation $actor, Desk $desk, array $input, TicketSource $source = TicketSource::WEB): Ticket
    {
        if ($actor instanceof User) {
            Gate::forUser($actor)->authorize('create', [Ticket::class, $desk]);
        }

        $isContact = $actor instanceof Contact;

        $validated = CreateTicketData::validateAndCreateWith($input, [
            ...($isContact ? ['status' => ['prohibited'], 'priority' => ['prohibited'], 'due_at' => ['prohibited']] : []),
            'assignee_ids' => [! $actor instanceof Contact ? 'sometimes' : 'prohibited'],
            'mailbox_id' => [$actor instanceof Contact ? 'sometimes' : 'prohibited'],
            'message_id' => [$actor instanceof Contact ? 'sometimes' : 'prohibited'],
            ...($isContact ? ['custom_fields' => ['prohibited']] : CustomFieldRules::for($desk, creating: true)),
        ])->filled();

        return DB::transaction(function () use ($actor, $desk, $validated, $source): Ticket {
            $ticket = Ticket::create([
                ...Arr::only($validated, ['subject', 'status', 'priority', 'due_at', 'mailbox_id']),
                'source' => $source,
                'custom_fields' => CustomFieldRules::cast($desk, $validated['custom_fields'] ?? []),
                'desk_id' => $desk->getKey(),
                'requester_id' => $validated['requester_id'] ?? Contact::findOrCreateByEmail($validated['requester']['email'], $validated['requester']['name'])->getKey(),
            ]);

            $opening = filled($validated['body'] ?? null)
                ? $this->addTicketComment->handle($actor, $ticket, Arr::only($validated, ['body', 'message_id']), opening: true)
                : null;

            $ticket->recordEvent(TicketEventType::CREATED, $actor, $opening ? ['comment_id' => $opening->id] : []);

            foreach ($opening ? ($validated['attachments'] ?? []) : [] as $file) {
                $this->addTicketAttachment->handle($actor, $ticket, ['file' => $file], $opening);
            }

            if (! $actor instanceof Contact && isset($validated['assignee_ids'])) {
                $this->syncTicketAssignees->handle($actor, $ticket, ['user_ids' => $validated['assignee_ids']]);
            }

            return $ticket;
        });
    }
}
