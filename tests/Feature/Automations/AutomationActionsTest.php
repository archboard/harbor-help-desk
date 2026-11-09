<?php

use App\Automations\AutomationRegistry;
use App\Automations\Context;
use App\Enums\AutomationRunStatus;
use App\Enums\CustomFieldType;
use App\Enums\DeskRole;
use App\Enums\NotificationType;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\WorkspaceRole;
use App\Mail\TicketReplyMail;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\Mailbox;
use App\Models\SlaPolicy;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AutomationNotice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->ticket = Ticket::factory()->create(['priority' => TicketPriority::NORMAL]);
    $this->desk = $this->ticket->desk;
});

/**
 * Runs the steps as an enabled automation on the ticket, through the real registry.
 *
 * @param  list<array{string, array<string, mixed>}>  $actions  Action key and config.
 */
function runActions(array $actions, ?Ticket $ticket = null): AutomationRun
{
    $steps = array_map(fn (array $action, int $index): array => [
        'id' => "s$index", 'type' => 'action', 'action' => $action[0], 'config' => $action[1],
    ], $actions, array_keys($actions));

    Automation::query()->delete();
    Automation::factory()->enabled()->create([
        'name' => 'Bot',
        'definition' => ['trigger' => ['type' => 'ticket.created', 'config' => []], 'steps' => $steps],
    ]);

    ($ticket ?? test()->ticket)->recordEvent(TicketEventType::CREATED, null);

    return AutomationRun::query()->latest('id')->firstOrFail();
}

test('set_field changes the status, priority, subject and a relative due date as the automation', function () {
    $this->freezeTime();

    $run = runActions([
        ['set_field', ['field' => 'ticket.status', 'value' => 'pending']],
        ['set_field', ['field' => 'ticket.priority', 'value' => 'urgent']],
        ['set_field', ['field' => 'ticket.subject', 'value' => 'Renamed']],
        ['set_field', ['field' => 'ticket.due_at', 'value' => ['relative' => ['amount' => 2, 'unit' => 'days']]]],
    ]);

    $this->ticket->refresh();

    expect($run->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($this->ticket->status)->toBe(TicketStatus::PENDING)
        ->and($this->ticket->priority)->toBe(TicketPriority::URGENT)
        ->and($this->ticket->subject)->toBe('Renamed')
        ->and($this->ticket->due_at->toIso8601String())->toBe(now()->addDays(2)->toIso8601String())
        ->and($this->ticket->events()->where('automation_run_id', $run->id)->count())->toBe(4);
});

test('set_field sets and clears an absolute due date and sets a custom field', function () {
    $field = CustomField::factory()->select(['Small', 'Large'])->create();
    $this->desk->customFields()->attach($field, ['workspace_id' => $this->workspace->id]);

    $run = runActions([
        ['set_field', ['field' => 'ticket.due_at', 'value' => '2030-01-02T03:04:05+00:00']],
        ['set_field', ['field' => "custom_field.$field->id", 'value' => 'Large']],
    ]);

    expect($run->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($this->ticket->refresh()->due_at->toIso8601String())->toBe('2030-01-02T03:04:05+00:00')
        ->and($this->ticket->custom_fields)->toBe([$field->id => 'Large']);

    $clear = runActions([['set_field', ['field' => 'ticket.due_at', 'value' => null]]]);

    expect($clear->status)->toBe(AutomationRunStatus::COMPLETED)->and($this->ticket->refresh()->due_at)->toBeNull();
});

test('set_field fails the run when the custom field is not enabled on the desk', function () {
    $field = CustomField::factory()->create(['type' => CustomFieldType::TEXT]);

    $run = runActions([['set_field', ['field' => "custom_field.$field->id", 'value' => 'x']]]);

    expect($run->status)->toBe(AutomationRunStatus::FAILED)->and($this->ticket->refresh()->custom_fields)->toBe([]);
});

test('tags adds, removes and replaces tags ignoring case', function () {
    $this->ticket->tags()->attach(Tag::factory()->create(['name' => 'Billing']), ['workspace_id' => $this->workspace->id]);

    runActions([['tags', ['mode' => 'add', 'names' => ['billing', 'vip']]]]);
    expect($this->ticket->tags()->pluck('name')->sort()->values()->all())->toBe(['Billing', 'vip']);

    runActions([['tags', ['mode' => 'remove', 'names' => ['BILLING']]]]);
    expect($this->ticket->tags()->pluck('name')->all())->toBe(['vip']);

    $run = runActions([['tags', ['mode' => 'replace', 'names' => ['spam']]]]);
    expect($this->ticket->tags()->pluck('name')->all())->toBe(['spam'])
        ->and($run->log[0]['result'])->toBe('Tags are now: spam.');
});

test('assign adds, replaces and clears assignees', function () {
    $ann = deskMember($this->workspace, $this->desk, attributes: ['name' => 'Ann']);
    $bob = deskMember($this->workspace, $this->desk, attributes: ['name' => 'Bob']);

    runActions([['assign', ['mode' => 'add', 'user_ids' => [$ann->id]]]]);
    runActions([['assign', ['mode' => 'add', 'user_ids' => [$bob->id]]]]);
    expect($this->ticket->assignees()->pluck('users.id')->sort()->values()->all())->toEqualCanonicalizing([$ann->id, $bob->id]);

    runActions([['assign', ['mode' => 'replace', 'user_ids' => [$bob->id]]]]);
    expect($this->ticket->assignees()->pluck('users.id')->all())->toBe([$bob->id]);

    runActions([['assign', ['mode' => 'unassign_all']]]);
    expect($this->ticket->assignees()->count())->toBe(0);
});

test('assign picks by round robin on its desk, and skips tickets on another desk', function () {
    $ann = deskMember($this->workspace, $this->desk);
    $other = Desk::factory()->create();
    $elsewhere = Ticket::factory()->create(['desk_id' => $other->id]);

    $skipped = runActions([['assign', ['mode' => 'round_robin', 'desk_id' => $this->desk->id]]], $elsewhere);
    expect($skipped->status)->toBe(AutomationRunStatus::COMPLETED)->and($elsewhere->assignees()->count())->toBe(0);

    runActions([['assign', ['mode' => 'load_balanced', 'desk_id' => $this->desk->id]]]);
    expect($this->ticket->assignees()->pluck('users.id')->all())->toBe([$ann->id]);

    $event = $this->ticket->events()->where('type', TicketEventType::ASSIGNEE_ADDED)->sole();
    expect($event->actor_type)->toBe('automation')->and($event->actorName())->toBe('Bot');
});

test('move_desk moves the ticket', function () {
    $other = Desk::factory()->create();

    $run = runActions([['move_desk', ['desk_id' => $other->id]]]);

    expect($run->status)->toBe(AutomationRunStatus::COMPLETED)->and($this->ticket->refresh()->desk_id)->toBe($other->id);
});

test('set_sla_policy sets the ticket override and clears it', function () {
    $policy = SlaPolicy::factory()->create();

    runActions([['set_sla_policy', ['sla_policy_id' => $policy->id]]]);
    expect($this->ticket->refresh()->sla_policy_id)->toBe($policy->id);

    runActions([['set_sla_policy', ['sla_policy_id' => null]]]);
    expect($this->ticket->refresh()->sla_policy_id)->toBeNull();
});

test('set_reminder sets a ticket-level reminder with a rendered note', function () {
    $this->freezeTime();

    $run = runActions([['set_reminder', ['after' => ['amount' => 3, 'unit' => 'hours'], 'note' => 'Check {{ ticket.number }} ({{ agent.name }})']]]);
    $reminder = $this->ticket->reminders()->sole();

    expect($run->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($reminder->isTicketLevel())->toBeTrue()
        ->and($reminder->remind_at->toIso8601String())->toBe(now()->addHours(3)->toIso8601String())
        ->and($reminder->note)->toBe("Check {$this->ticket->number} (Bot)")
        ->and($this->ticket->events()->where('type', TicketEventType::REMINDER_SET)->exists())->toBeTrue();
});

test('every action describes what it would do without changing anything', function () {
    $ann = deskMember($this->workspace, $this->desk, attributes: ['name' => 'Ann']);
    $policy = SlaPolicy::factory()->create(['name' => 'Gold']);
    $other = Desk::factory()->create(['name' => 'Billing']);
    $context = new Context($this->ticket);
    $actions = app(AutomationRegistry::class)->actions();

    $descriptions = [
        'set_field' => [['field' => 'ticket.priority', 'value' => 'urgent'], 'Would set priority to urgent.'],
        'tags' => [['mode' => 'add', 'names' => ['vip']], 'Would set the tags to: vip.'],
        'assign' => [['mode' => 'add', 'user_ids' => [$ann->id]], 'Would add Ann as assignees.'],
        'move_desk' => [['desk_id' => $other->id], 'Would move the ticket to Billing.'],
        'set_sla_policy' => [['sla_policy_id' => $policy->id], 'Would set the SLA policy to Gold.'],
        'set_reminder' => [['after' => ['amount' => 1, 'unit' => 'days']], 'Would set a reminder in 1 days.'],
    ];

    foreach ($descriptions as $key => [$config, $expected]) {
        expect($actions[$key]->describe($context, $config))->toBe($expected);
    }

    expect($this->ticket->events()->count())->toBe(0)->and($this->ticket->refresh()->priority)->toBe(TicketPriority::NORMAL);
});

test('actions fail the run instead of touching another workspace\'s data', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    [$deskB, $userB, $policyB, $fieldB, $ticketB] = $other->execute(function () use ($other) {
        $desk = Desk::sole();
        $user = deskMember($other, $desk);

        return [$desk, $user, SlaPolicy::factory()->create(), CustomField::factory()->create(), Ticket::factory()->create()];
    });
    $ticketBDeskId = $ticketB->desk_id;

    $bad = [
        'move_desk' => ['desk_id' => $deskB->id],
        'assign' => ['mode' => 'add', 'user_ids' => [$userB->id]],
        'assign (rotation)' => ['mode' => 'round_robin', 'desk_id' => $deskB->id],
        'set_sla_policy' => ['sla_policy_id' => $policyB->id],
        'set_field' => ['field' => "custom_field.$fieldB->id", 'value' => 'x'],
    ];

    foreach ($bad as $name => $config) {
        $run = runActions([[explode(' ', $name)[0], $config]]);

        expect($run->status)->toBe(AutomationRunStatus::FAILED, $name);
    }

    $other->execute(function () use ($ticketB, $ticketBDeskId, $policyB) {
        expect($ticketB->refresh()->desk_id)->toBe($ticketBDeskId)
            ->and($ticketB->sla_policy_id)->toBeNull()
            ->and($ticketB->assignees()->count())->toBe(0)
            ->and($ticketB->custom_fields)->toBe([])
            ->and($policyB->fresh())->not->toBeNull();
    });
    expect($this->ticket->refresh()->desk_id)->toBe($this->desk->id)->and($this->ticket->sla_policy_id)->toBeNull();
});

function commentBy(Ticket $ticket, Model $author, string $body): TicketComment
{
    $comment = new TicketComment(['body' => $body, 'is_internal' => false]);
    $comment->author()->associate($author);
    $ticket->comments()->save($comment);

    return $comment;
}

test('add_note adds an internal note as the automation with the placeholders filled in', function () {
    Mail::fake();

    $run = runActions([['add_note', ['body' => 'Check {{ ticket.subject }} for {{ requester.first_name }} - {{ agent.name }}']]]);
    $comment = $this->ticket->comments()->sole();

    expect($run->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($comment->is_internal)->toBeTrue()
        ->and($comment->author)->toBeInstanceOf(Automation::class)
        ->and($comment->body)->toBe("Check {$this->ticket->subject} for ".Str::before($this->ticket->requester->name, ' ').' - Bot')
        ->and($comment->body)->not->toContain('{{');
    Mail::assertNothingSent();
});

test('send_reply emails a public reply, filling in the triggering message', function () {
    Mail::fake();
    $mailbox = Mailbox::factory()->create(['default_desk_id' => $this->desk->id]);
    $this->ticket->update(['mailbox_id' => $mailbox->id]);
    commentBy($this->ticket, $this->ticket->requester, 'Where is my parcel?');

    Automation::factory()->enabled()->triggeredBy('ticket.comment_added', 'send_reply', ['body' => 'You wrote: {{ message.body }}'])->create(['name' => 'Bot']);
    $this->ticket->recordEvent(TicketEventType::COMMENT_ADDED, $this->ticket->requester, ['comment_id' => $this->ticket->comments()->sole()->id]);

    $reply = $this->ticket->comments()->reorder()->latest('id')->first();

    expect($reply->is_internal)->toBeFalse()
        ->and($reply->author)->toBeInstanceOf(Automation::class)
        ->and($reply->body)->toBe('You wrote: Where is my parcel?');
    Mail::assertSent(TicketReplyMail::class);
});

test('send_reply is skipped when the last reply bounced, until a newer reply goes out', function () {
    Mail::fake();
    $bot = Automation::factory()->create();
    $post = fn (string $body) => commentBy($this->ticket, $bot, $body);
    $reply = [['send_reply', ['body' => 'Hello']]];

    $post('Earlier');
    $this->travel(1)->seconds();
    $this->ticket->recordEvent(TicketEventType::EMAIL_BOUNCED, null, ['reason' => 'mailbox full']);

    $run = runActions($reply);
    expect($run->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($run->log[0]['result'])->toBe('Skipped: the last reply to the requester bounced.')
        ->and($this->ticket->comments()->count())->toBe(1);

    $this->travel(1)->seconds();
    $post('Newer');
    expect(runActions($reply)->log[0]['result'])->toBe('Sent a reply.');
});

test('send_reply is skipped when it is triggered by a bounce or by an automation message', function () {
    Mail::fake();
    $bot = Automation::factory()->create();
    $note = commentBy($this->ticket, $bot, 'From a bot');

    Automation::factory()->enabled()->triggeredBy('ticket.comment_added', 'send_reply', ['body' => 'Hello'])->create();
    $this->ticket->recordEvent(TicketEventType::COMMENT_ADDED, $bot, ['comment_id' => $note->id]);

    expect(AutomationRun::query()->latest('id')->firstOrFail()->log[0]['result'])->toBe('Skipped: the triggering message was written by an automation.');

    // No trigger fires on a bounce yet, so the guard is exercised on the action itself.
    $context = new Context($this->ticket, $this->ticket->recordEvent(TicketEventType::EMAIL_BOUNCED, null, ['reason' => 'x']));
    expect(app(AutomationRegistry::class)->action('send_reply')->describe($context, ['body' => 'Hi']))->toBe('Skipped: the triggering event is a bounce.');

    expect($this->ticket->comments()->count())->toBe(1);
    Mail::assertNothingSent();
});

test('apply_macro runs the macro actions and posts its body as the macro says', function () {
    Mail::fake();
    $mailbox = Mailbox::factory()->create(['default_desk_id' => $this->desk->id]);
    $this->ticket->update(['mailbox_id' => $mailbox->id]);
    $note = Macro::factory()->create(['body' => 'Hi {{ requester.first_name }}', 'body_is_internal' => true, 'actions' => ['priority' => 'high']]);
    $reply = Macro::factory()->create(['body' => 'We are on it', 'body_is_internal' => false, 'actions' => ['add_tags' => ['macro']]]);

    $run = runActions([['apply_macro', ['macro_id' => $note->id]], ['apply_macro', ['macro_id' => $reply->id]]]);

    $comments = $this->ticket->comments()->with('author')->orderBy('id')->get();
    expect($run->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::HIGH)
        ->and($this->ticket->tags()->pluck('name')->all())->toBe(['macro'])
        ->and($comments->pluck('is_internal')->all())->toBe([true, false])
        ->and($comments[0]->body)->toBe('Hi '.Str::before($this->ticket->load('requester')->requester->name, ' '))
        ->and($comments[1]->author)->toBeInstanceOf(Automation::class);
    Mail::assertSent(TicketReplyMail::class, 1);
});

test('apply_macro fails the run for a personal macro, a missing one or one of another desk', function () {
    $personal = Macro::factory()->personal(deskMember($this->workspace, $this->desk))->create();
    $elsewhere = Macro::factory()->forDesk(Desk::factory()->create())->create();

    foreach ([$personal->id, $elsewhere->id, 'mac_missing'] as $id) {
        $run = runActions([['apply_macro', ['macro_id' => $id]]]);
        expect($run->status)->toBe(AutomationRunStatus::FAILED, $id);
    }
});

test('notify sends the rendered template to the chosen recipients who can view the ticket', function () {
    Notification::fake();
    $assignee = deskMember($this->workspace, $this->desk);
    $lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD);
    $member = deskMember($this->workspace, $this->desk);
    $this->ticket->assignees()->attach($assignee, ['workspace_id' => $this->workspace->id]);
    $config = fn (string $recipients, array $extra = []) => ['recipients' => $recipients, 'subject' => 'Look at #{{ ticket.number }}', 'body' => 'By {{ agent.name }}', ...$extra];

    runActions([['notify', $config('assignees')]]);
    Notification::assertSentTo($assignee, AutomationNotice::class, fn (AutomationNotice $n) => $n->title === "Look at #{$this->ticket->number}"
        && $n->body === 'By Bot' && $n->toArray($assignee)['ticket_id'] === $this->ticket->id && $n->databaseType($assignee) === 'automation');
    Notification::assertCount(1);

    runActions([['notify', $config('desk_leads')]]);
    Notification::assertSentTo($lead, AutomationNotice::class);
    Notification::assertNotSentTo($member, AutomationNotice::class);

    runActions([['notify', $config('desk_members')]]);
    Notification::assertSentToTimes($member, AutomationNotice::class, 1);

    runActions([['notify', $config('users', ['user_ids' => [$member->id]])]]);
    Notification::assertSentToTimes($member, AutomationNotice::class, 2);

    // Someone who can't view the ticket is skipped.
    $this->workspace->users()->updateExistingPivot($member->id, ['role' => WorkspaceRole::AGENT]);
    $this->desk->users()->detach($member);
    $run = runActions([['notify', $config('users', ['user_ids' => [$member->id]])]]);
    expect($run->status)->toBe(AutomationRunStatus::COMPLETED)->and($run->log[0]['result'])->toBe('Nobody to notify.');
});

test('notify follows the recipient preferences: in-app on and email off by default', function () {
    $user = deskMember($this->workspace, $this->desk);

    expect($user->notificationChannels($this->workspace, NotificationType::AUTOMATION))->toBe(['database']);
});

test('a failed run tells the creator once an hour, only while they can still manage the automation', function () {
    Notification::fake();
    $this->freezeTime();
    $creator = User::factory()->create();
    $this->workspace->users()->attach($creator, ['role' => WorkspaceRole::ADMIN]);
    $automation = Automation::factory()->enabled()->triggeredBy('ticket.created', 'move_desk', ['desk_id' => 'dsk_missing'])->create(['name' => 'Router', 'created_by' => $creator->id]);
    $fire = fn () => $this->ticket->recordEvent(TicketEventType::CREATED, null);

    $fire();
    Notification::assertSentToTimes($creator, AutomationNotice::class, 1);
    Notification::assertSentTo($creator, AutomationNotice::class, fn (AutomationNotice $n) => $n->title === 'Automation failed: Router'
        && str_contains($n->body, 'The desk no longer exists'));

    $fire();
    Notification::assertSentToTimes($creator, AutomationNotice::class, 1);

    $this->travel(61)->minutes();
    $fire();
    Notification::assertSentToTimes($creator, AutomationNotice::class, 2);

    // Not for skipped or completed runs, and not once the creator is an agent who can't manage it.
    $this->workspace->users()->updateExistingPivot($creator->id, ['role' => WorkspaceRole::AGENT]);
    $this->travel(61)->minutes();
    $fire();
    Notification::assertSentToTimes($creator, AutomationNotice::class, 2);

    $this->workspace->users()->updateExistingPivot($creator->id, ['role' => WorkspaceRole::ADMIN]);
    $automation->update(['definition' => ['trigger' => ['type' => 'ticket.created', 'config' => []], 'steps' => [['id' => 's', 'type' => 'action', 'action' => 'add_note', 'config' => ['body' => 'ok']]]]]);
    $this->travel(61)->minutes();
    $fire();
    Notification::assertSentToTimes($creator, AutomationNotice::class, 2);
});

test('the automation notice email shows the subject, body and ticket link, and no ticket footer without a ticket', function () {
    $user = deskMember($this->workspace, $this->desk);
    $automation = Automation::factory()->create(['name' => 'Router']);

    $withTicket = new AutomationNotice($automation, $this->ticket, 'Heads up', 'Please look at this.');
    $mail = $withTicket->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Heads up')
        ->and($html)->toContain('Please look at this.')
        ->and($html)->toContain($this->workspace->url("/tickets/{$this->ticket->id}"))
        ->and($html)->toContain("Ticket #{$this->ticket->number}");

    $html = (string) (new AutomationNotice($automation, null, 'Automation failed: Router', 'It broke.'))->toMail($user)->render();

    expect($html)->toContain('It broke.')->and($html)->not->toContain('Ticket #');
});
