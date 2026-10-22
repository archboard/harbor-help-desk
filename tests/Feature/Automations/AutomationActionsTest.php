<?php

use App\Automations\AutomationRegistry;
use App\Automations\Context;
use App\Enums\AutomationRunStatus;
use App\Enums\CustomFieldType;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\SlaPolicy;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\Workspace;
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
