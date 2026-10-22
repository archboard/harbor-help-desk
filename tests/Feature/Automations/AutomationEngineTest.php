<?php

use App\Actions\Tickets\CheckSlaTargets;
use App\Automations\AutomationRegistry;
use App\Automations\Triggers\CommentAdded;
use App\Automations\Triggers\FieldChanged;
use App\Automations\Triggers\SlaEvent;
use App\Automations\Triggers\TicketCreated;
use App\Enums\AutomationRunStatus;
use App\Enums\SlaMetric;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketSlaTarget;
use Illuminate\Support\Facades\Exceptions;
use Tests\Concerns\InWorkspace;
use Tests\Fakes\Automations\CrashAction;
use Tests\Fakes\Automations\RejectAction;
use Tests\Fakes\Automations\SetPriorityAction;

uses(InWorkspace::class);

beforeEach(function () {
    app()->instance(AutomationRegistry::class, new AutomationRegistry(
        [TicketCreated::class, CommentAdded::class, FieldChanged::class, SlaEvent::class],
        [SetPriorityAction::class, RejectAction::class, CrashAction::class],
    ));

    $this->ticket = Ticket::factory()->create(['priority' => TicketPriority::NORMAL]);
});

/**
 * @param  list<array<string, mixed>>  $steps
 * @param  array<string, mixed>  $attributes
 */
function automation(array $steps, string $trigger = 'ticket.created', array $config = [], array $attributes = []): Automation
{
    return Automation::factory()->enabled()->create([
        'trigger_type' => $trigger,
        'definition' => ['trigger' => ['type' => $trigger, 'config' => $config], 'steps' => $steps],
        ...$attributes,
    ]);
}

function setPriority(string $id, string $priority): array
{
    return ['id' => $id, 'type' => 'action', 'action' => 'fake.set_priority', 'config' => ['priority' => $priority]];
}

function failingAction(string $id, string $action = 'fake.reject'): array
{
    return ['id' => $id, 'type' => 'action', 'action' => $action, 'config' => []];
}

function whenPriorityIs(string $id, string $priority): array
{
    return ['id' => $id, 'type' => 'condition', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => 'ticket.priority', 'operator' => 'is', 'value' => $priority]]];
}

function created(Ticket $ticket): void
{
    $ticket->recordEvent(TicketEventType::CREATED, null);
}

test('a matching automation runs its steps as itself and logs each one', function () {
    $automation = automation([whenPriorityIs('c1', 'normal'), setPriority('a1', 'urgent')], attributes: ['name' => 'Escalator']);

    created($this->ticket);

    $run = AutomationRun::sole();
    $change = $this->ticket->events()->where('type', TicketEventType::PRIORITY_CHANGED)->sole();

    expect($this->ticket->refresh()->priority)->toBe(TicketPriority::URGENT)
        ->and($run)->status->toBe(AutomationRunStatus::COMPLETED)
        ->automation_id->toBe($automation->id)
        ->ticket_id->toBe($this->ticket->id)
        ->workspace_id->toBe($this->workspace->id)
        ->depth->toBe(0)
        ->error->toBeNull()
        ->and($run->log)->toHaveCount(2)
        ->and($run->log[0])->toMatchArray(['step_id' => 'c1', 'type' => 'condition', 'passed' => true])
        ->and($run->log[0]['condition']['rules'][0])->toMatchArray(['field' => 'ticket.priority', 'expected' => 'normal', 'actual' => 'normal', 'passed' => true])
        ->and($run->log[1])->toMatchArray(['step_id' => 'a1', 'type' => 'action', 'action' => 'fake.set_priority', 'result' => 'Priority set to urgent'])
        ->and($run->definition->steps)->toHaveCount(2)
        ->and($change->automation_run_id)->toBe($run->id)
        ->and($change->actorName())->toBe('Escalator');
});

test('a failed condition ends the run as completed without matching', function () {
    automation([whenPriorityIs('c1', 'low'), setPriority('a1', 'urgent')]);

    created($this->ticket);

    $run = AutomationRun::sole();

    expect($this->ticket->refresh()->priority)->toBe(TicketPriority::NORMAL)
        ->and($run->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($run->state)->toBe(['matched' => false])
        ->and($run->log)->toHaveCount(1)
        ->and($run->log[0]['passed'])->toBeFalse();
});

test('paths run the first matching branch, else the otherwise steps', function (string $priority, string $expected, ?string $branch) {
    $this->ticket->update(['priority' => $priority]);
    $paths = ['id' => 'p1', 'type' => 'paths', 'branches' => [
        ['name' => 'Urgent', 'condition' => ['type' => 'group', 'match' => 'all', 'rules' => whenPriorityIs('x', 'urgent')['rules']], 'steps' => [setPriority('a1', 'high')]],
        ['name' => 'Also urgent', 'condition' => ['type' => 'group', 'match' => 'all', 'rules' => whenPriorityIs('x', 'urgent')['rules']], 'steps' => [setPriority('a2', 'low')]],
    ], 'else' => [setPriority('a3', 'normal')]];
    automation([$paths]);

    created($this->ticket);

    $run = AutomationRun::sole();

    expect($this->ticket->refresh()->priority->value)->toBe($expected)
        ->and($run->log[0])->toMatchArray(['step_id' => 'p1', 'type' => 'paths', 'branch' => $branch])
        ->and(array_column($run->log, 'step_id'))->toBe(['p1', $branch === 'Urgent' ? 'a1' : 'a3']);
})->with([
    'first matching branch' => ['urgent', 'high', 'Urgent'],
    'otherwise' => ['low', 'normal', 'else'],
]);

test('a stop ends the run and skips the later automations', function () {
    automation([['id' => 's1', 'type' => 'stop'], setPriority('a1', 'urgent')], attributes: ['position' => 1]);
    automation([setPriority('a2', 'high')], attributes: ['position' => 2]);

    created($this->ticket);

    $run = AutomationRun::sole();

    expect($run->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($run->state)->toBe(['stopped' => true])
        ->and($run->log)->toHaveCount(1)
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::NORMAL);
});

test('automations run in position order and see each other\'s changes', function () {
    automation([whenPriorityIs('c1', 'high'), setPriority('a1', 'low')], attributes: ['position' => 2]);
    automation([setPriority('a2', 'high')], attributes: ['position' => 1]);

    created($this->ticket);

    expect($this->ticket->refresh()->priority)->toBe(TicketPriority::LOW)
        ->and(AutomationRun::query()->orderBy('id')->get()->pluck('status')->all())
        ->toBe([AutomationRunStatus::COMPLETED, AutomationRunStatus::COMPLETED]);
});

test('a rejected action fails the run with the messages and later automations still run', function () {
    automation([failingAction('a1')], attributes: ['position' => 1]);
    automation([setPriority('a2', 'urgent')], attributes: ['position' => 2]);

    created($this->ticket);

    $failed = AutomationRun::query()->where('status', AutomationRunStatus::FAILED)->sole();

    expect($failed->error)->toBe('The status is invalid. The name is required.')
        ->and($failed->log[0])->toMatchArray(['step_id' => 'a1', 'error' => 'The status is invalid. The name is required.'])
        ->and(AutomationRun::query()->where('status', AutomationRunStatus::COMPLETED)->count())->toBe(1)
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::URGENT);
});

test('a crashing action and a delay on an empty date fail the run instead of the job', function () {
    automation([failingAction('a1', 'fake.crash')], attributes: ['position' => 1]);
    automation([['id' => 'd1', 'type' => 'delay', 'until' => ['field' => 'ticket.due_at', 'offset' => 0]]], attributes: ['position' => 2]);
    automation([failingAction('a3', 'fake.missing')], attributes: ['position' => 3]);
    automation([setPriority('a4', 'urgent')], attributes: ['position' => 4]);

    created($this->ticket);

    expect(AutomationRun::query()->orderBy('id')->pluck('error')->all())
        ->toBe(['The step failed unexpectedly.', 'The delay can not wait until a date that is not set.', 'The action “fake.missing” does not exist.', null])
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::URGENT);
});

test('an unexpected exception stores a generic message and is reported', function () {
    Exceptions::fake();
    automation([failingAction('a1', 'fake.crash')]);

    created($this->ticket);

    $run = AutomationRun::sole();

    expect($run->status)->toBe(AutomationRunStatus::FAILED)
        ->and($run->error)->toBe('The step failed unexpectedly.')
        ->and(json_encode($run->log))->not->toContain('Boom');
    Exceptions::assertReported(fn (RuntimeException $exception) => $exception->getMessage() === 'Boom');
});

test('only enabled automations whose trigger and desk match run', function () {
    $otherDesk = Desk::factory()->create();

    automation([setPriority('a1', 'low')], attributes: ['enabled' => false]);
    automation([setPriority('a2', 'low')], 'ticket.comment_added');
    automation([setPriority('a3', 'low')], 'ticket.created', ['sources' => ['api']]);
    automation([setPriority('a4', 'low')], attributes: ['desk_id' => $otherDesk->id]);
    $own = automation([setPriority('a5', 'high')], attributes: ['desk_id' => $this->ticket->desk_id]);
    $everywhere = automation([whenPriorityIs('c6', 'high')]);

    created($this->ticket);

    expect(AutomationRun::query()->pluck('automation_id')->sort()->values()->all())->toBe(collect([$own->id, $everywhere->id])->sort()->values()->all());

    // Moving the ticket changes which desk-scoped automations see it.
    $this->ticket->forceFill(['desk_id' => $otherDesk->id])->save();
    AutomationRun::query()->delete();
    created($this->ticket);

    expect(AutomationRun::query()->pluck('automation_id')->all())->toHaveCount(2)
        ->and(AutomationRun::query()->where('automation_id', $own->id)->exists())->toBeFalse();
});

test('a ticket.sla automation runs when the checker breaches a target', function () {
    $automation = automation([setPriority('a1', 'urgent')], 'ticket.sla', ['kind' => 'breached', 'metrics' => ['first_response']]);
    TicketSlaTarget::create([
        'ticket_id' => $this->ticket->id,
        'metric' => SlaMetric::FIRST_RESPONSE,
        'target_minutes' => 60,
        'started_at' => now()->subHours(2),
        'warn_at' => now()->subMinutes(75),
        'due_at' => now()->subHour(),
    ]);

    app(CheckSlaTargets::class)->handle();

    $run = AutomationRun::sole();

    expect($run)->automation_id->toBe($automation->id)->status->toBe(AutomationRunStatus::COMPLETED)
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::URGENT);
});
