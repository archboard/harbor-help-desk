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
use App\Enums\WorkspaceRole;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

function deskMember(Workspace $workspace, Desk $desk, DeskRole $role = DeskRole::AGENT, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $workspace->users()->attach($user, ['role' => WorkspaceRole::AGENT]);
    $desk->users()->attach($user, ['role' => $role, 'workspace_id' => $workspace->id]);

    return $user;
}

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

test('updating a ticket logs one event per changed field', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Printer jam']);
    $agent = deskMember($this->workspace, $ticket->desk);

    app(UpdateTicket::class)->handle($agent, $ticket, [
        'subject' => 'Printer jam',
        'priority' => 'urgent',
        'status' => 'pending',
        'due_at' => '2026-11-02 15:00:00',
    ]);

    expect($ticket->events()->get()->map->description()->all())->toBe([
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
    expect($ticket->events()->sole()->description())->toBe("{$agent->name} moved the ticket to South.");
});

test('assignees are synced and each change is logged', function () {
    $ticket = Ticket::factory()->create();
    $actor = deskMember($this->workspace, $ticket->desk, attributes: ['name' => 'Jane Doe']);
    $john = deskMember($this->workspace, $ticket->desk, attributes: ['name' => 'John Smith']);

    app(SyncTicketAssignees::class)->handle($actor, $ticket, ['user_ids' => [$actor->id, $john->id]]);
    app(SyncTicketAssignees::class)->handle($actor, $ticket, ['user_ids' => [$actor->id]]);

    expect($ticket->assignees()->pluck('users.id')->all())->toBe([$actor->id]);
    expect($ticket->events()->get()->map->description()->all())->toBe([
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
