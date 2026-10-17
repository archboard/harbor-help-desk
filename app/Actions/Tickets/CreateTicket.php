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
    public function __construct(protected SyncTicketAssignees $syncTicketAssignees) {}

    /**
     * Open a ticket on an active desk. The requester is an existing contact (`requester_id`) or is
     * found or created by email (`requester.email`, `requester.name`). `custom_fields` maps the
     * desk's enabled field ids to values.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Desk $desk, array $input): Ticket
    {
        Gate::forUser($actor)->authorize('create', [Ticket::class, $desk]);

        $validated = Validator::make($input, [
            'subject' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'requester_id' => ['required_without:requester', 'string', Rule::exists('contacts', 'id')->where('workspace_id', Workspace::current()?->getKey())],
            'requester' => ['required_without:requester_id', 'array:name,email'],
            'requester.email' => ['required_with:requester', 'email', 'max:255'],
            'requester.name' => ['required_with:requester', 'string', 'max:255'],
            'assignee_ids' => ['sometimes', 'array'],
            ...CustomFieldRules::for($desk, creating: true),
        ])->validate();

        return DB::transaction(function () use ($actor, $desk, $validated): Ticket {
            $ticket = Ticket::create([
                ...Arr::only($validated, ['subject', 'status', 'priority', 'due_at']),
                'custom_fields' => CustomFieldRules::cast($desk, $validated['custom_fields'] ?? []),
                'desk_id' => $desk->getKey(),
                'requester_id' => $validated['requester_id'] ?? $this->findOrCreateContact($validated['requester'])->getKey(),
            ]);

            $ticket->recordEvent(TicketEventType::CREATED, $actor);

            if (isset($validated['assignee_ids'])) {
                $this->syncTicketAssignees->handle($actor, $ticket, ['user_ids' => $validated['assignee_ids']]);
            }

            return $ticket;
        });
    }

    /**
     * @param  array{name: string, email: string}  $requester
     */
    protected function findOrCreateContact(array $requester): Contact
    {
        // ponytail: two concurrent tickets from a brand-new email can race; the unique index
        // rejects the second. Retry on UniqueConstraintViolationException if that shows up.
        return Contact::query()->whereRaw('lower(email) = lower(?)', [$requester['email']])->first()
            ?? Contact::create($requester);
    }
}
