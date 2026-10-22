<?php

use App\Actions\Email\ReceiveInboundEmail;
use App\Actions\Tickets\AutoAssignTicket;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\MoveTicket;
use App\Enums\AssignmentStrategy;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Mail\Inbound\InboundEmail;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Ticket;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->desk = Desk::factory()->create(['assignment_strategy' => AssignmentStrategy::ROUND_ROBIN]);
    $this->ann = deskMember($this->workspace, $this->desk);
    $this->bob = deskMember($this->workspace, $this->desk);
    $this->cat = deskMember($this->workspace, $this->desk);
});

function assigneeIds(Ticket $ticket): array
{
    return $ticket->assignees()->pluck('users.id')->all();
}

test('round robin rotates through the members', function () {
    $picked = collect(range(1, 4))->map(fn () => app(AutoAssignTicket::class)->handle(Ticket::factory()->for($this->desk)->create())->id);

    expect($picked->take(3)->sort()->values()->all())->toBe(collect([$this->ann, $this->bob, $this->cat])->pluck('id')->sort()->values()->all())
        ->and($picked[3])->toBe($picked[0]);
});

test('load balanced picks the least loaded member and breaks ties by last assignment', function () {
    $this->desk->update(['assignment_strategy' => AssignmentStrategy::LOAD_BALANCED]);
    foreach ([$this->ann, $this->ann, $this->bob] as $member) {
        Ticket::factory()->for($this->desk)->create(['status' => TicketStatus::OPEN])->assignees()->attach($member, ['workspace_id' => $this->workspace->id]);
    }
    Ticket::factory()->for($this->desk)->create(['status' => TicketStatus::SOLVED])->assignees()->attach($this->cat, ['workspace_id' => $this->workspace->id]);
    $this->desk->users()->updateExistingPivot($this->cat->id, ['last_assigned_at' => now()]);

    $first = app(AutoAssignTicket::class)->handle(Ticket::factory()->for($this->desk)->create());
    // Cat (0 active tickets) first, then Cat and Bob tie at 1 and Bob has never been picked.
    $second = app(AutoAssignTicket::class)->handle(Ticket::factory()->for($this->desk)->create());

    expect($first->is($this->cat))->toBeTrue()
        ->and($second->is($this->bob))->toBeTrue();
});

test('away and out-of-rotation members are skipped', function () {
    $this->desk->users()->updateExistingPivot($this->ann->id, ['in_rotation' => false]);
    $this->workspace->users()->updateExistingPivot($this->bob->id, ['away_at' => now()]);

    $assignee = app(AutoAssignTicket::class)->handle(Ticket::factory()->for($this->desk)->create());

    expect($assignee->is($this->cat))->toBeTrue();
});

test('nobody eligible leaves the ticket unassigned', function () {
    foreach ([$this->ann, $this->bob, $this->cat] as $member) {
        $this->desk->users()->updateExistingPivot($member->id, ['in_rotation' => false]);
    }
    $ticket = Ticket::factory()->for($this->desk)->create();

    expect(app(AutoAssignTicket::class)->handle($ticket))->toBeNull()
        ->and(assigneeIds($ticket))->toBeEmpty();
});

test('a ticket on an archived desk is left unassigned', function () {
    $ticket = Ticket::factory()->for($this->desk)->create();
    $this->desk->delete();

    expect(app(AutoAssignTicket::class)->handle($ticket))->toBeNull()
        ->and(assigneeIds($ticket))->toBeEmpty();
});

test('manual desks, assigned tickets and tickets that are not open are untouched', function () {
    $manual = Desk::factory()->create();
    deskMember($this->workspace, $manual);
    $assigned = Ticket::factory()->for($this->desk)->create();
    $assigned->assignees()->attach($this->ann, ['workspace_id' => $this->workspace->id]);
    $solved = Ticket::factory()->for($this->desk)->create(['status' => TicketStatus::SOLVED]);

    expect(app(AutoAssignTicket::class)->handle(Ticket::factory()->for($manual)->create()))->toBeNull()
        ->and(app(AutoAssignTicket::class)->handle($assigned))->toBeNull()
        ->and(assigneeIds($assigned))->toBe([$this->ann->id])
        ->and(app(AutoAssignTicket::class)->handle($solved))->toBeNull();
});

test('an explicit strategy overrides the desk and sets last_assigned_at', function () {
    $manual = Desk::factory()->create();
    $dan = deskMember($this->workspace, $manual);

    $assignee = app(AutoAssignTicket::class)->handle(Ticket::factory()->for($manual)->create(), AssignmentStrategy::ROUND_ROBIN);

    expect($assignee->is($dan))->toBeTrue()
        ->and($manual->users()->first()->pivot->last_assigned_at)->not->toBeNull();
});

test('a new ticket is assigned by Harbor', function () {
    $ticket = app(CreateTicket::class)->handle($this->ann, $this->desk, [
        'subject' => 'Help',
        'requester' => ['email' => 'pat@example.com', 'name' => 'Pat'],
    ]);

    $event = $ticket->events()->where('type', TicketEventType::ASSIGNEE_ADDED)->sole();

    expect(assigneeIds($ticket))->toHaveCount(1)
        ->and($event->actor_type)->toBeNull()
        ->and($event->description())->toStartWith('Harbor assigned');
});

test('moving a ticket into an auto desk assigns it', function () {
    $manual = Desk::factory()->create();
    $mover = deskMember($this->workspace, $manual);
    $this->desk->users()->attach($mover, ['role' => 'agent', 'workspace_id' => $this->workspace->id]);
    $ticket = Ticket::factory()->for($manual)->create();

    app(MoveTicket::class)->handle($mover, $ticket, $this->desk);

    expect(assigneeIds($ticket))->toHaveCount(1);
});

test('an inbound email ticket is assigned', function () {
    Notification::fake();
    $mailbox = Mailbox::factory()->create(['default_desk_id' => $this->desk->id, 'address' => 'support@acme.com']);

    $comment = app(ReceiveInboundEmail::class)->handle($mailbox, new InboundEmail(
        messageId: 'auto@mail.example.com',
        inReplyTo: null,
        references: [],
        fromEmail: 'pat@example.com',
        fromName: 'Pat Lee',
        recipients: ['support@acme.com'],
        subject: 'Printer jam',
        text: 'Jammed.',
        attachments: [],
        headers: [],
    ));

    expect(assigneeIds($comment->ticket))->toHaveCount(1);
});
