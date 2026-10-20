<?php

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\BulkUpdateTickets;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\UpdateTicket;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\Desk;
use App\Models\Ticket;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->desk = Desk::sole();
    $this->actor = deskMember($this->workspace, $this->desk);
    $this->colleague = deskMember($this->workspace, $this->desk);
    $this->ticket = Ticket::factory()->for($this->desk)->create();
    $this->ticket->assignees()->attach([$this->actor->id, $this->colleague->id], ['workspace_id' => $this->workspace->id]);
});

test('a change by another agent notifies the other assignees', function () {
    app(UpdateTicket::class)->handle($this->actor, $this->ticket, ['status' => TicketStatus::PENDING->value]);

    expect($this->colleague->notifications()->where('type', 'ticket_updated')->count())->toBe(1)
        ->and($this->colleague->notifications()->sole()->data['ticket_id'])->toBe($this->ticket->id)
        ->and($this->actor->notifications()->count())->toBe(0);
});

test('an agent reply notifies the other assignees', function () {
    app(AddTicketComment::class)->handle($this->actor, $this->ticket, ['body' => 'On it']);

    expect($this->colleague->notifications()->where('type', 'ticket_updated')->count())->toBe(1)
        ->and($this->actor->notifications()->count())->toBe(0);
});

test('a second change while the first is unread does not notify again, until it is read', function () {
    $update = fn (TicketStatus $status) => app(UpdateTicket::class)->handle($this->actor, $this->ticket, ['status' => $status->value]);

    $update(TicketStatus::PENDING);
    $update(TicketStatus::ON_HOLD);
    expect($this->colleague->notifications()->count())->toBe(1);

    $this->colleague->unreadNotifications->markAsRead();
    $update(TicketStatus::OPEN);
    expect($this->colleague->notifications()->count())->toBe(2);
});

test('a bulk update leaves one unread update per ticket and recipient', function () {
    $other = Ticket::factory()->for($this->desk)->create();
    $other->assignees()->attach($this->colleague, ['workspace_id' => $this->workspace->id]);

    app(BulkUpdateTickets::class)->handle($this->actor, [
        'ticket_ids' => [$this->ticket->id, $other->id],
        'status' => TicketStatus::PENDING->value,
    ]);
    app(UpdateTicket::class)->handle($this->actor, $this->ticket, ['priority' => 'high']);

    expect($this->colleague->notifications()->get()->map(fn ($notification) => $notification->data['ticket_id'])->sort()->values()->all())
        ->toBe(collect([$this->ticket->id, $other->id])->sort()->values()->all());
});

test('creating a ticket is not also a ticket update', function () {
    app(CreateTicket::class)->handle($this->actor, $this->desk, [
        'subject' => 'Help',
        'requester' => ['name' => 'Rita', 'email' => 'rita@example.com'],
        'assignee_ids' => [$this->colleague->id],
    ]);

    expect($this->colleague->notifications()->pluck('type')->all())->toBe(['assigned']);
});

test('a due date is described in the recipient\'s timezone', function () {
    $this->colleague->update(['timezone' => 'America/New_York']);

    $this->ticket->recordEvent(TicketEventType::DUE_DATE_CHANGED, $this->actor, ['from' => null, 'to' => '2026-10-19T18:30:00+00:00']);

    expect($this->colleague->notifications()->sole()->data['description'])->toContain('2:30 PM');
});

test('a mentioned assignee gets mentioned only, and other assignees still get the update', function () {
    $other = deskMember($this->workspace, $this->desk);
    $this->ticket->assignees()->attach($other->id, ['workspace_id' => $this->workspace->id]);

    app(AddTicketComment::class)->handle($this->actor, $this->ticket, [
        'body' => 'Look, @Jane',
        'is_internal' => true,
        'mentioned_user_ids' => [$this->colleague->id],
    ]);

    expect($this->colleague->notifications()->pluck('type')->all())->toBe(['mentioned'])
        ->and($other->notifications()->pluck('type')->all())->toBe(['ticket_updated'])
        ->and($this->actor->notifications()->count())->toBe(0);
});

test('a mentioned desk member who is not an assignee is notified', function () {
    $mentioned = deskMember($this->workspace, $this->desk);

    app(AddTicketComment::class)->handle($this->actor, $this->ticket, [
        'body' => 'FYI',
        'is_internal' => true,
        'mentioned_user_ids' => [$mentioned->id],
    ]);

    expect($mentioned->notifications()->pluck('type')->all())->toBe(['mentioned']);
});
