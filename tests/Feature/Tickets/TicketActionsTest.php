<?php

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\DeleteTicket;
use App\Actions\Tickets\MoveTicket;
use App\Actions\Tickets\RestoreTicket;
use App\Actions\Tickets\SyncTicketAssignees;
use App\Actions\Tickets\UpdateTicket;
use App\Enums\DeskRole;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

test('creating a ticket finds the requester by email regardless of case and logs it', function () {
    $desk = Desk::factory()->create();
    $agent = deskMember($this->workspace, $desk);
    $contact = Contact::factory()->create(['email' => 'pat@example.com']);

    $ticket = app(CreateTicket::class)->handle($agent, $desk, [
        'subject' => 'Printer jam',
        'priority' => 'high',
        'requester' => ['name' => 'Pat', 'email' => 'PAT@example.com'],
    ]);

    expect($ticket->fresh())
        ->number->toBe(1)
        ->requester_id->toBe($contact->id)
        ->priority->toBe(TicketPriority::HIGH);
    expect(Contact::count())->toBe(1);
    expect($ticket->events()->pluck('type')->all())->toBe([TicketEventType::CREATED]);
});

test('creating a ticket with a new email creates the contact and can assign agents', function () {
    $desk = Desk::factory()->create();
    $agent = deskMember($this->workspace, $desk);

    $ticket = app(CreateTicket::class)->handle($agent, $desk, [
        'subject' => 'Printer jam',
        'requester' => ['name' => 'Pat Lee', 'email' => 'pat@example.com'],
        'assignee_ids' => [$agent->id],
    ]);

    expect($ticket->requester->only('name', 'email'))->toBe(['name' => 'Pat Lee', 'email' => 'pat@example.com']);
    expect($ticket->assignees->modelKeys())->toBe([$agent->id]);
});

test('tickets cannot be created on a desk the agent is not on', function () {
    $agent = deskMember($this->workspace, Desk::factory()->create());

    expect(fn () => app(CreateTicket::class)->handle($agent, Desk::factory()->create(), [
        'subject' => 'Printer jam',
        'requester_id' => Contact::factory()->create()->id,
    ]))->toThrow(AuthorizationException::class);
});

test('tickets cannot be created for another workspace contact', function () {
    $desk = Desk::factory()->create();
    $agent = deskMember($this->workspace, $desk);
    $foreignContact = Workspace::factory()->create()->execute(fn () => Contact::factory()->create());

    expect(fn () => app(CreateTicket::class)->handle($agent, $desk, ['subject' => 'Printer jam', 'requester_id' => $foreignContact->id]))
        ->toThrow(ValidationException::class);
});

test('a contact can open a ticket by email without a desk policy', function () {
    $desk = Desk::factory()->create();
    $contact = Contact::factory()->create();
    $mailbox = Mailbox::factory()->create(['default_desk_id' => $desk->id]);

    $ticket = app(CreateTicket::class)->handle($contact, $desk, [
        'subject' => 'Printer jam',
        'requester_id' => $contact->id,
        'body' => 'It is jammed again.',
        'mailbox_id' => $mailbox->id,
        'message_id' => '<abc@mail.example.com>',
    ]);

    expect($ticket->mailbox_id)->toBe($mailbox->id);
    expect($ticket->comments()->sole())
        ->message_id->toBe('<abc@mail.example.com>')
        ->author->is($contact)->toBeTrue();
    expect($ticket->events()->first()->actor->is($contact))->toBeTrue();
});

test('only contacts can set email fields and contacts cannot assign', function (string $actor, array $input) {
    $desk = Desk::factory()->create();
    $contact = Contact::factory()->create();
    $actor = $actor === 'agent' ? deskMember($this->workspace, $desk) : $contact;

    expect(fn () => app(CreateTicket::class)->handle($actor, $desk, ['subject' => 'Printer jam', 'requester_id' => $contact->id, ...$input]))
        ->toThrow(ValidationException::class);
})->with([
    'agent sets message_id' => ['agent', ['body' => 'Hi', 'message_id' => '<abc@example.com>']],
    'agent sets mailbox_id' => ['agent', ['mailbox_id' => 'mbx_x']],
    'contact assigns' => ['contact', ['assignee_ids' => ['usr_x']]],
]);

test('a contact cannot attach a ticket to another workspace mailbox', function () {
    $desk = Desk::factory()->create();
    $contact = Contact::factory()->create();
    $foreignMailbox = Workspace::factory()->create()->execute(fn () => Mailbox::factory()->create());

    expect(fn () => app(CreateTicket::class)->handle($contact, $desk, ['subject' => 'Printer jam', 'requester_id' => $contact->id, 'mailbox_id' => $foreignMailbox->id]))
        ->toThrow(ValidationException::class);
});

test('updating a ticket logs one event per changed field', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Printer jam']);
    $agent = deskMember($this->workspace, $ticket->desk);

    app(UpdateTicket::class)->handle($agent, $ticket, [
        'subject' => 'Printer jam',
        'priority' => 'urgent',
        'status' => 'pending',
        'due_at' => '2026-11-02 15:00:00',
    ]);

    expect($ticket->events()->with('actor')->get()->map->description()->all())->toBe([
        "{$agent->name} changed the status to Pending.",
        "{$agent->name} changed the priority to Urgent.",
        "{$agent->name} set the due date to Mon, Nov 2, 2026 3:00 PM.",
    ]);
    expect($ticket->fresh()->priority)->toBe(TicketPriority::URGENT);
});

test('moving a ticket needs access to the target desk', function () {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);
    $otherDesk = Desk::factory()->create(['name' => 'South']);

    expect(fn () => app(MoveTicket::class)->handle($agent, $ticket, $otherDesk))
        ->toThrow(AuthorizationException::class);

    $otherDesk->users()->attach($agent, ['role' => DeskRole::AGENT, 'workspace_id' => $this->workspace->id]);
    app(MoveTicket::class)->handle($agent, $ticket, $otherDesk);

    expect($ticket->fresh()->desk_id)->toBe($otherDesk->id);
    expect($ticket->events()->with('actor')->sole()->description())->toBe("{$agent->name} moved the ticket to South.");
});

test('assignees are synced and each change is logged', function () {
    $ticket = Ticket::factory()->create();
    $actor = deskMember($this->workspace, $ticket->desk, attributes: ['name' => 'Jane Doe']);
    $john = deskMember($this->workspace, $ticket->desk, attributes: ['name' => 'John Smith']);

    app(SyncTicketAssignees::class)->handle($actor, $ticket, ['user_ids' => [$actor->id, $john->id]]);
    app(SyncTicketAssignees::class)->handle($actor, $ticket, ['user_ids' => [$actor->id]]);

    expect($ticket->assignees()->pluck('users.id')->all())->toBe([$actor->id]);
    expect($ticket->events()->with('actor')->get()->map->description()->all())->toBe([
        'Jane Doe assigned Jane Doe.',
        'Jane Doe assigned John Smith.',
        'Jane Doe unassigned John Smith.',
    ]);
});

test('only desk members can be assigned', function () {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);
    $elsewhere = deskMember($this->workspace, Desk::factory()->create());

    expect(fn () => app(SyncTicketAssignees::class)->handle($agent, $ticket, ['user_ids' => [$elsewhere->id]]))
        ->toThrow(ValidationException::class, 'Only members of the ticket’s desk can be assigned.');
});

test('desk leads can delete and restore tickets but agents cannot', function () {
    $ticket = Ticket::factory()->create();
    $lead = deskMember($this->workspace, $ticket->desk, DeskRole::LEAD);
    $agent = deskMember($this->workspace, $ticket->desk);

    expect(fn () => app(DeleteTicket::class)->handle($agent, $ticket))->toThrow(AuthorizationException::class);

    app(DeleteTicket::class)->handle($lead, $ticket);
    expect($ticket->fresh()->trashed())->toBeTrue();

    app(RestoreTicket::class)->handle($lead, $ticket);
    expect($ticket->fresh()->trashed())->toBeFalse();
    expect($ticket->events()->pluck('type')->all())->toBe([TicketEventType::DELETED, TicketEventType::RESTORED]);
});

function ticketWithFields(array ...$definitions): array
{
    $desk = Desk::factory()->create();
    $fields = array_map(fn (array $attributes): CustomField => CustomField::factory()->create($attributes), $definitions);
    $desk->customFields()->attach(array_column($fields, 'id'), ['workspace_id' => $desk->workspace_id]);

    return [$desk, $fields];
}

test('custom field values are validated by type on create and stored as JSON types', function (array $definition, mixed $input, mixed $stored, bool $valid) {
    [$desk, [$field]] = ticketWithFields($definition);
    $agent = deskMember($this->workspace, $desk);
    $attempt = fn () => app(CreateTicket::class)->handle($agent, $desk, [
        'subject' => 'Printer jam',
        'requester_id' => Contact::factory()->create()->id,
        'custom_fields' => [$field->id => $input],
    ]);

    if ($valid) {
        expect($attempt()->fresh()->custom_fields)->toBe([$field->id => $stored]);
    } else {
        expect($attempt)->toThrow(ValidationException::class);
    }
})->with([
    'text' => [['type' => 'text'], 'ACME-42', 'ACME-42', true],
    'text too long' => [['type' => 'text'], str_repeat('a', 256), null, false],
    'text as array' => [['type' => 'text'], ['a'], null, false],
    'textarea' => [['type' => 'textarea'], str_repeat('a', 1000), str_repeat('a', 1000), true],
    'number from string' => [['type' => 'number'], '42', 42, true],
    'number with decimals' => [['type' => 'number'], '4.2', null, false],
    'decimal from string' => [['type' => 'decimal'], '4.5', 4.5, true],
    'decimal not numeric' => [['type' => 'decimal'], 'abc', null, false],
    'date' => [['type' => 'date'], '2026-11-02', '2026-11-02', true],
    'date wrong format' => [['type' => 'date'], '11/02/2026', null, false],
    'checkbox true' => [['type' => 'checkbox'], '1', true, true],
    'checkbox zero' => [['type' => 'checkbox'], '0', false, true],
    'checkbox not boolean' => [['type' => 'checkbox'], 'maybe', null, false],
    'select' => [['type' => 'select', 'options' => ['Small', 'Large']], 'Large', 'Large', true],
    'select not an option' => [['type' => 'select', 'options' => ['Small', 'Large']], 'Huge', null, false],
    'multi-select' => [['type' => 'multi_select', 'options' => ['A', 'B', 'C']], ['C', 'A'], ['C', 'A'], true],
    'multi-select not an option' => [['type' => 'multi_select', 'options' => ['A', 'B']], ['A', 'Z'], null, false],
    'multi-select repeated' => [['type' => 'multi_select', 'options' => ['A', 'B']], ['A', 'A'], null, false],
    'multi-select as string' => [['type' => 'multi_select', 'options' => ['A', 'B']], 'A', null, false],
]);

test('required custom fields must be given on create and cannot be cleared on update', function () {
    [$desk, [$required, $optional]] = ticketWithFields(['is_required' => true], []);
    $agent = deskMember($this->workspace, $desk);
    $input = ['subject' => 'Printer jam', 'requester_id' => Contact::factory()->create()->id];

    expect(fn () => app(CreateTicket::class)->handle($agent, $desk, $input))->toThrow(ValidationException::class);
    expect(fn () => app(CreateTicket::class)->handle($agent, $desk, [...$input, 'custom_fields' => [$required->id => '']]))
        ->toThrow(ValidationException::class);

    $ticket = app(CreateTicket::class)->handle($agent, $desk, [...$input, 'custom_fields' => [$required->id => 'ACME-42', $optional->id => null]]);
    expect($ticket->fresh()->custom_fields)->toBe([$required->id => 'ACME-42']);

    expect(fn () => app(UpdateTicket::class)->handle($agent, $ticket, ['custom_fields' => [$required->id => null]]))
        ->toThrow(ValidationException::class);
});

test('a required field does not block updates to tickets that predate it', function () {
    [$desk, [$required]] = ticketWithFields(['is_required' => true]);
    $ticket = Ticket::factory()->for($desk)->create();

    app(UpdateTicket::class)->handle(deskMember($this->workspace, $desk), $ticket, ['subject' => 'Renamed']);

    expect($ticket->fresh())->subject->toBe('Renamed')->custom_fields->toBe([]);
});

test('custom fields the desk has not enabled are rejected', function () {
    [$desk] = ticketWithFields([]);
    $other = CustomField::factory()->create();
    $agent = deskMember($this->workspace, $desk);
    $ticket = Ticket::factory()->for($desk)->create();
    $bare = Desk::factory()->create();

    expect(fn () => app(UpdateTicket::class)->handle($agent, $ticket, ['custom_fields' => [$other->id => 'x']]))
        ->toThrow(ValidationException::class);
    expect(fn () => app(CreateTicket::class)->handle(deskMember($this->workspace, $bare), $bare, [
        'subject' => 'Printer jam',
        'requester_id' => Contact::factory()->create()->id,
        'custom_fields' => [$other->id => 'x'],
    ]))->toThrow(ValidationException::class);
});

test('updating custom fields patches values and logs only real changes', function () {
    [$desk, [$order, $vip, $size]] = ticketWithFields(['name' => 'Order number'], ['name' => 'VIP', 'type' => 'checkbox'], ['name' => 'Sizes', 'type' => 'multi_select', 'options' => ['S', 'M']]);
    $agent = deskMember($this->workspace, $desk);
    $ticket = Ticket::factory()->for($desk)->create(['custom_fields' => [$order->id => 'ACME-42']]);

    app(UpdateTicket::class)->handle($agent, $ticket, ['custom_fields' => [$vip->id => true, $size->id => ['S', 'M']]]);
    app(UpdateTicket::class)->handle($agent, $ticket, ['custom_fields' => [$vip->id => true, $order->id => null]]);
    app(UpdateTicket::class)->handle($agent, $ticket, ['custom_fields' => [$order->id => null]]);

    expect($ticket->fresh()->custom_fields)->toBe([$vip->id => true, $size->id => ['S', 'M']]);
    expect($ticket->events()->with('actor')->get()->map->description()->all())->toBe([
        'Jane Doe set VIP to Yes.',
        'Jane Doe set Sizes to S, M.',
        'Jane Doe cleared Order number.',
    ]);
});

test('clearing the last custom field keeps a JSON object', function () {
    [$desk, [$order]] = ticketWithFields([]);
    $ticket = Ticket::factory()->for($desk)->create(['custom_fields' => [$order->id => 'x']]);

    app(UpdateTicket::class)->handle(deskMember($this->workspace, $desk), $ticket, ['custom_fields' => [$order->id => null]]);

    expect(DB::table('tickets')->where('id', $ticket->id)->value('custom_fields'))->toBe('{}');
});
