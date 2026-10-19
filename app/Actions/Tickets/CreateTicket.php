<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
     * @param  array<string, mixed>  $input
     */
    public function handle(User|Contact $actor, Desk $desk, array $input): Ticket
    {
        if ($actor instanceof User) {
            Gate::forUser($actor)->authorize('create', [Ticket::class, $desk]);
        }

        $validated = Validator::make($input, [
            'subject' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'requester_id' => ['required_without:requester', 'string', Rule::exists('contacts', 'id')->where('workspace_id', Workspace::current()?->getKey())],
            'requester' => ['required_without:requester_id', 'array:name,email'],
            'requester.email' => ['required_with:requester', 'email', 'max:255'],
            'requester.name' => ['required_with:requester', 'string', 'max:255'],
            'assignee_ids' => [$actor instanceof User ? 'sometimes' : 'prohibited', 'array'],
            'mailbox_id' => [$actor instanceof Contact ? 'sometimes' : 'prohibited', 'nullable', 'string', Rule::exists('mailboxes', 'id')->where('workspace_id', Workspace::current()?->getKey())],
            'message_id' => [$actor instanceof Contact ? 'sometimes' : 'prohibited', 'nullable', 'string', 'max:998'],
            'body' => ['required_with:attachments', 'nullable', 'string', 'max:65535'],
            'attachments' => ['sometimes', 'array', 'max:20'],
            'attachments.*' => AddTicketAttachment::rules(),
            ...CustomFieldRules::for($desk, creating: true),
        ])->validate();

        return DB::transaction(function () use ($actor, $desk, $validated): Ticket {
            $ticket = Ticket::create([
                ...Arr::only($validated, ['subject', 'status', 'priority', 'due_at', 'mailbox_id']),
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
