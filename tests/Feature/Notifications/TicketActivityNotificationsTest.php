<?php

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\SyncTicketAssignees;
use App\Enums\NotificationType;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TicketActivity;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    Notification::fake();
    $this->desk = Desk::sole();
    $this->actor = deskMember($this->workspace, $this->desk);
    $this->colleague = deskMember($this->workspace, $this->desk);
    $this->ticket = Ticket::factory()->for($this->desk)->create();
});

test('a new unassigned ticket notifies desk members except its creator', function () {
    app(CreateTicket::class)->handle($this->actor, $this->desk, [
        'subject' => 'Help',
        'requester' => ['name' => 'Rita', 'email' => 'rita@example.com'],
    ]);

    Notification::assertSentTo($this->colleague, TicketActivity::class, fn (TicketActivity $n) => $n->type === NotificationType::NEW_TICKET);
    Notification::assertNotSentTo($this->actor, TicketActivity::class);
    Notification::assertCount(1);
});

test('a new ticket assigned at creation is not announced to the desk', function () {
    app(CreateTicket::class)->handle($this->actor, $this->desk, [
        'subject' => 'Help',
        'requester' => ['name' => 'Rita', 'email' => 'rita@example.com'],
        'assignee_ids' => [$this->colleague->id],
    ]);

    Notification::assertSentTo($this->colleague, TicketActivity::class, fn (TicketActivity $n) => $n->type === NotificationType::ASSIGNED);
    Notification::assertCount(1);
});

test('an assignee is notified unless they assigned themselves', function () {
    app(SyncTicketAssignees::class)->handle($this->actor, $this->ticket, ['user_ids' => [$this->actor->id, $this->colleague->id]]);

    Notification::assertSentTo($this->colleague, TicketActivity::class, fn (TicketActivity $n) => $n->type === NotificationType::ASSIGNED);
    Notification::assertNotSentTo($this->actor, TicketActivity::class);
});

test('a requester reply notifies the assignees only', function () {
    $this->ticket->assignees()->attach($this->colleague, ['workspace_id' => $this->workspace->id]);

    app(AddTicketComment::class)->handle($this->ticket->requester, $this->ticket, ['body' => 'Any news?']);

    Notification::assertSentTo($this->colleague, TicketActivity::class, fn (TicketActivity $n) => $n->type === NotificationType::REQUESTER_REPLIED);
    Notification::assertNotSentTo($this->actor, TicketActivity::class);
});

test('a requester reply on an unassigned ticket notifies the desk members', function () {
    app(AddTicketComment::class)->handle($this->ticket->requester, $this->ticket, ['body' => 'Any news?']);

    Notification::assertSentTo([$this->actor, $this->colleague], TicketActivity::class);
});

test('an agent reply or note notifies nobody', function () {
    app(AddTicketComment::class)->handle($this->actor, $this->ticket, ['body' => 'Working on it']);

    Notification::assertNothingSent();
});

test('people who left the workspace or cannot view the ticket are skipped', function () {
    $this->workspace->users()->detach($this->colleague);
    $outsider = User::factory()->create();
    $this->workspace->users()->attach($outsider, ['role' => 'agent']);

    app(AddTicketComment::class)->handle($this->ticket->requester, $this->ticket, ['body' => 'Hello']);
    Notification::assertSentTo($this->actor, TicketActivity::class);
    Notification::assertNotSentTo($this->colleague, TicketActivity::class);

    $this->ticket->assignees()->attach($outsider, ['workspace_id' => $this->workspace->id]);
    app(AddTicketComment::class)->handle($this->ticket->requester, $this->ticket, ['body' => 'Hello again']);
    Notification::assertNotSentTo($outsider, TicketActivity::class);
});

test('the notification carries the ticket and a readable description', function () {
    app(SyncTicketAssignees::class)->handle($this->actor, $this->ticket, ['user_ids' => [$this->colleague->id]]);

    Notification::assertSentTo($this->colleague, TicketActivity::class, function (TicketActivity $notification) {
        $data = $notification->toArray($this->colleague);

        return $data['type'] === 'assigned'
            && $data['ticket_id'] === $this->ticket->id
            && $data['ticket_number'] === $this->ticket->number
            && $data['actor'] === $this->actor->name
            && $data['description'] === "{$this->actor->name} assigned {$this->colleague->name}."
            && in_array('broadcast', $notification->via($this->colleague), true)
            && str_contains($notification->toMail($this->colleague)->actionUrl, $this->ticket->id);
    });
});

test('the queued listener runs inside the workspace of the event', function () {
    config(['queue.default' => 'database']);
    app(AddTicketComment::class)->handle($this->ticket->requester, $this->ticket, ['body' => 'Hello']);
    Workspace::forgetCurrent();

    expect(DB::table('jobs')->where('payload', 'like', '%SendTicketActivityNotifications%')->count())->toBe(1);
    Notification::assertNothingSent();

    Artisan::call('queue:work', ['--stop-when-empty' => true, '--queue' => 'default']);

    Notification::assertSentTo([$this->actor, $this->colleague], TicketActivity::class);
});
