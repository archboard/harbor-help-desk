<?php

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\MoveTicket;
use App\Actions\Tickets\SetTicketReminder;
use App\Actions\Tickets\SyncTicketAssignees;
use App\Actions\Tickets\SyncTicketTags;
use App\Actions\Tickets\UpdateTicket;
use App\Data\Tickets\TicketReminderData;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\Automation;
use App\Models\Desk;
use App\Models\Ticket;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->automation = Automation::factory()->create(['name' => 'Triage bot']);
    $this->ticket = Ticket::factory()->create();
});

test('an automation changes a ticket without a policy check and the trail names it', function () {
    $assignee = deskMember($this->workspace, $this->ticket->desk);
    $otherDesk = Desk::factory()->create();

    app(UpdateTicket::class)->handle($this->automation, $this->ticket, ['status' => TicketStatus::PENDING->value]);
    app(SyncTicketTags::class)->handle($this->automation, $this->ticket, ['names' => ['Urgent']]);
    app(SyncTicketAssignees::class)->handle($this->automation, $this->ticket, ['user_ids' => [$assignee->id]]);
    app(AddTicketComment::class)->handle($this->automation, $this->ticket, ['body' => 'Looking into it', 'is_internal' => true]);
    app(SetTicketReminder::class)->handle($this->automation, $this->ticket, ['remind_at' => now()->addDay()->toIso8601String(), 'for_assignees' => true]);
    app(MoveTicket::class)->handle($this->automation, $this->ticket, $otherDesk);

    $events = $this->ticket->events()->with('actor')->get();

    expect($events)->toHaveCount(6)
        ->and($events->every(fn ($event) => $event->actorName() === 'Triage bot'))->toBeTrue()
        ->and($events->every(fn ($event) => $event->actor_type === 'automation' && $event->actor_id === $this->automation->id))->toBeTrue()
        ->and($this->ticket->comments()->sole()->author->is($this->automation))->toBeTrue()
        ->and($this->ticket->reminders()->sole()->created_by)->toBeNull();
});

test('an automation creates a ticket and assigns it', function () {
    $desk = $this->ticket->desk;
    $assignee = deskMember($this->workspace, $desk);

    $ticket = app(CreateTicket::class)->handle($this->automation, $desk, [
        'subject' => 'From a rule',
        'requester' => ['email' => 'pat@example.com', 'name' => 'Pat'],
        'assignee_ids' => [$assignee->id],
    ]);

    expect($ticket->assignees()->sole()->is($assignee))->toBeTrue()
        ->and($ticket->events()->with('actor')->first()->description())->toBe('Triage bot created a ticket.');
});

test('a reminder set by an automation serializes without a creator', function () {
    $reminder = app(SetTicketReminder::class)->handle($this->automation, $this->ticket, [
        'remind_at' => now()->addDay()->toIso8601String(),
        'for_assignees' => true,
    ]);

    expect(TicketReminderData::from($reminder)->toArray())
        ->toMatchArray(['for_assignees' => true, 'created_by' => null]);
});

test('the trail reads a deleted automation once it is gone', function () {
    $event = $this->ticket->recordEvent(TicketEventType::CREATED, $this->automation);
    $this->automation->delete();

    expect($event->fresh()->actorName())->toBe('A deleted automation');
});
