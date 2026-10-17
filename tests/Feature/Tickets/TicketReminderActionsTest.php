<?php

use App\Actions\Tickets\CancelTicketReminder;
use App\Actions\Tickets\SendDueTicketReminders;
use App\Actions\Tickets\SetTicketReminder;
use App\Enums\DeskRole;
use App\Models\Ticket;
use App\Models\TicketReminder;
use App\Models\Workspace;
use App\Notifications\TicketReminderDue;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

test('personal reminders are private and ticket-level ones are logged', function () {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);

    $personal = app(SetTicketReminder::class)->handle($agent, $ticket, ['remind_at' => '2026-11-02 15:00:00', 'note' => 'Call back']);
    $shared = app(SetTicketReminder::class)->handle($agent, $ticket, ['remind_at' => '2026-11-02 15:00:00', 'for_assignees' => true]);

    expect($personal->user_id)->toBe($agent->id);
    expect($shared->isTicketLevel())->toBeTrue();
    expect($ticket->events()->sole()->description())->toBe('Jane Doe set a reminder for Mon, Nov 2, 2026 3:00 PM.');
});

test('reminders must be in the future', function () {
    $ticket = Ticket::factory()->create();

    expect(fn () => app(SetTicketReminder::class)->handle(deskMember($this->workspace, $ticket->desk), $ticket, ['remind_at' => now()->subMinute()->toDateTimeString()]))
        ->toThrow(ValidationException::class);
});

test('due personal reminders notify their user once', function () {
    Notification::fake();
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);
    $due = TicketReminder::factory()->for($ticket)->create(['created_by' => $agent->id, 'remind_at' => now()->subMinute()]);
    TicketReminder::factory()->for($ticket)->create(['created_by' => $agent->id, 'remind_at' => now()->addMinute()]);

    expect(app(SendDueTicketReminders::class)->handle())->toBe(1);
    expect(app(SendDueTicketReminders::class)->handle())->toBe(0);

    Notification::assertSentToTimes($agent, TicketReminderDue::class, 1);
    expect($due->fresh()->sent_at)->not->toBeNull();
});

test('due ticket-level reminders notify the current assignees who can still see the ticket', function () {
    Notification::fake();
    $ticket = Ticket::factory()->create();
    $assignee = deskMember($this->workspace, $ticket->desk);
    $formerMember = deskMember($this->workspace, $ticket->desk);
    $ticket->assignees()->attach([$assignee->id, $formerMember->id], ['workspace_id' => $this->workspace->id]);
    $ticket->desk->users()->detach($formerMember);
    TicketReminder::factory()->for($ticket)->ticketLevel()->create(['created_by' => $assignee->id, 'remind_at' => now()->subMinute()]);

    app(SendDueTicketReminders::class)->handle();

    Notification::assertSentTo($assignee, TicketReminderDue::class, fn (TicketReminderDue $notification) => $notification->toArray($assignee)['ticket_id'] === $ticket->id);
    Notification::assertNotSentTo($formerMember, TicketReminderDue::class);
});

test('reminders on deleted tickets are marked sent without notifying', function () {
    Notification::fake();
    $reminder = TicketReminder::factory()->create(['remind_at' => now()->subMinute()]);
    $reminder->ticket->delete();

    app(SendDueTicketReminders::class)->handle();

    Notification::assertNothingSent();
    expect($reminder->fresh()->sent_at)->not->toBeNull();
});

test('the command sends due reminders in every workspace', function () {
    Notification::fake();
    $reminders = collect([Workspace::factory()->create(), Workspace::factory()->create()])
        ->map(fn (Workspace $workspace) => $workspace->execute(function () use ($workspace) {
            $ticket = Ticket::factory()->create();

            return TicketReminder::factory()->for($ticket)->create(['created_by' => deskMember($workspace, $ticket->desk)->id, 'remind_at' => now()->subMinute()]);
        }));

    $this->artisan('tickets:send-reminders')->assertSuccessful();

    expect(TicketReminder::withoutGlobalScope('workspace')->whereKey($reminders->pluck('id'))->whereNull('sent_at')->count())->toBe(0);
    Notification::assertCount(2);
});

test('people cancel their own reminders, and leads can cancel ticket-level ones', function () {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);
    $personal = TicketReminder::factory()->for($ticket)->create(['created_by' => $agent->id]);
    $shared = TicketReminder::factory()->for($ticket)->ticketLevel()->create(['created_by' => $agent->id]);
    $other = deskMember($this->workspace, $ticket->desk);

    expect(fn () => app(CancelTicketReminder::class)->handle($other, $personal))->toThrow(AuthorizationException::class);
    expect(fn () => app(CancelTicketReminder::class)->handle($other, $shared))->toThrow(AuthorizationException::class);

    app(CancelTicketReminder::class)->handle($agent, $personal);
    app(CancelTicketReminder::class)->handle(deskMember($this->workspace, $ticket->desk, DeskRole::LEAD), $shared);

    expect($ticket->reminders()->count())->toBe(0);
});
