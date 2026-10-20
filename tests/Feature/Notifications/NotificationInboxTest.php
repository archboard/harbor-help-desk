<?php

use App\Actions\Tickets\SendDueTicketReminders;
use App\Models\DatabaseNotification;
use App\Models\Ticket;
use App\Models\TicketReminder;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TicketReminderDue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->colleague = deskMember($this->workspace, $this->desk);
    $this->mine = storeNotification($this->agent, ['note' => 'Mine']);
    $this->theirs = storeNotification($this->colleague, ['note' => 'Theirs']);
});

/**
 * Store a reminder notification for the user in the current workspace.
 *
 * @param  array<string, mixed>  $data
 */
function storeNotification(User $user, array $data = []): DatabaseNotification
{
    return $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'reminder_due', 'data' => $data]);
}

test('a user only sees their own notifications in the current workspace', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $other->users()->attach($this->agent, ['role' => 'agent']);
    $other->execute(fn () => storeNotification($this->agent, ['note' => 'Elsewhere']));

    $this->actingAs($this->agent)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$this->mine->id])
        ->assertJsonPath('data.0.type', 'reminder_due');

    $this->actingAs($this->agent)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 1));
});

test('notifications are marked read one at a time or all at once, never someone else\'s', function () {
    $second = storeNotification($this->agent);
    $this->actingAs($this->agent);

    $this->postJson(route('notifications.read-one', $this->theirs->id))->assertNoContent();
    expect($this->theirs->refresh()->read_at)->toBeNull();

    $this->postJson(route('notifications.read-one', $this->mine->id))->assertNoContent();
    expect($this->mine->refresh()->read_at)->not->toBeNull()
        ->and($second->refresh()->read_at)->toBeNull();

    $this->postJson(route('notifications.read'))->assertNoContent();
    expect($second->refresh()->read_at)->not->toBeNull()
        ->and($this->theirs->refresh()->read_at)->toBeNull();
});

test('the API lists and marks notifications read for the token\'s workspace', function () {
    actAs($this->agent, $this->workspace);
    $url = 'http://acme.harbor.test/api/v1/notifications';

    $this->getJson($url)->assertOk()->assertJsonPath('data.*.id', [$this->mine->id]);

    $this->postJson("{$url}/read", ['ids' => [$this->theirs->id]])->assertNoContent();
    expect($this->mine->refresh()->read_at)->toBeNull();

    $this->postJson("{$url}/read", ['ids' => [$this->mine->id]])->assertNoContent();
    expect($this->mine->refresh()->read_at)->not->toBeNull();

    $this->postJson("{$url}/read", ['ids' => 'nope'])->assertJsonValidationErrors('ids');
});

test('a due reminder is stored in the inbox with its type and workspace', function () {
    $ticket = Ticket::factory()->for($this->desk)->create();
    $this->agent->workspaces()->updateExistingPivot($this->workspace->id, [
        'notification_preferences' => ['reminder_due' => ['mail' => false]],
    ]);
    TicketReminder::factory()->for($ticket)->create(['created_by' => $this->agent->id, 'remind_at' => now()->subMinute(), 'note' => 'Call back']);

    app(SendDueTicketReminders::class)->handle();

    $notification = $this->agent->notifications()->where('data->note', 'Call back')->sole();
    expect($notification->workspace_id)->toBe($this->workspace->id)
        ->and($notification->type)->toBe('reminder_due')
        ->and($notification->data)->toMatchArray(['ticket_id' => $ticket->id, 'ticket_number' => $ticket->number])
        ->and($notification->data)->not->toHaveKey('workspace_id');
});

test('a reminder emails only when the preference is on, and always pings the browser', function () {
    Notification::fake();
    $ticket = Ticket::factory()->for($this->desk)->create();
    $reminder = TicketReminder::factory()->for($ticket)->create(['created_by' => $this->agent->id, 'note' => 'Call back']);

    Notification::send($this->agent, new TicketReminderDue($reminder));
    Notification::assertSentTo($this->agent, TicketReminderDue::class, fn ($n, array $channels) => $channels === ['database', 'mail', 'broadcast']);

    $this->agent->workspaces()->updateExistingPivot($this->workspace->id, [
        'notification_preferences' => ['reminder_due' => ['mail' => false]],
    ]);
    Notification::send($this->agent->fresh(), new TicketReminderDue($reminder));
    Notification::assertSentToTimes($this->agent, TicketReminderDue::class, 2);
    Notification::assertSentTo($this->agent, TicketReminderDue::class, fn ($n, array $channels) => $channels === ['database', 'broadcast']);

    $mail = (new TicketReminderDue($reminder))->toMail($this->agent);
    expect($mail->subject)->toBe("Reminder: #{$ticket->number} {$ticket->subject}")
        ->and($mail->actionUrl)->toBe($this->workspace->url("/tickets/{$ticket->id}"));
});
