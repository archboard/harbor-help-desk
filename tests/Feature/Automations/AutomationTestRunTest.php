<?php

use App\Automations\SafeUrl;
use App\Enums\DeskRole;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\DatabaseNotification;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->desk = Desk::sole();
    $this->ticket = Ticket::factory()->for($this->desk)->create(['priority' => TicketPriority::NORMAL]);
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD);
    $this->agent = deskMember($this->workspace, $this->desk);
    $this->url = 'http://acme.harbor.test/api/v1/automations/test';

    app()->instance(SafeUrl::class, new SafeUrl(fn (string $host): array => ['hooks.example.com' => ['93.184.216.34']][$host] ?? []));
});

/**
 * @param  list<array<string, mixed>>  $steps
 * @return array<string, mixed>
 */
function dryDefinition(array $steps, string $trigger = 'ticket.created', array $config = []): array
{
    return ['trigger' => ['type' => $trigger, 'config' => $config], 'steps' => $steps];
}

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function dryAction(string $id, string $action, array $config): array
{
    return ['id' => $id, 'type' => 'action', 'action' => $action, 'config' => $config];
}

/**
 * @return array<string, mixed>
 */
function dryCondition(string $id, string $priority): array
{
    return ['id' => $id, 'type' => 'condition', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => 'ticket.priority', 'operator' => 'is', 'value' => $priority]]];
}

test('a dry run writes nothing and describes every action', function () {
    Queue::fake();
    Bus::fake();
    Notification::fake();
    $macro = Macro::factory()->create();
    $policy = SlaPolicy::factory()->create();
    $otherDesk = Desk::factory()->create();
    $steps = [
        dryAction('a1', 'set_field', ['field' => 'ticket.priority', 'value' => 'urgent']),
        dryAction('a2', 'tags', ['mode' => 'add', 'names' => ['vip']]),
        dryAction('a3', 'assign', ['mode' => 'add', 'user_ids' => [$this->agent->id]]),
        dryAction('a4', 'move_desk', ['desk_id' => $otherDesk->id]),
        dryAction('a5', 'set_sla_policy', ['sla_policy_id' => $policy->id]),
        dryAction('a6', 'add_note', ['body' => 'Note for {{ requester.name }}']),
        dryAction('a7', 'send_reply', ['body' => 'Hello {{ requester.first_name }}']),
        dryAction('a8', 'apply_macro', ['macro_id' => $macro->id]),
        dryAction('a9', 'notify', ['recipients' => 'users', 'user_ids' => [$this->agent->id], 'subject' => 'Look', 'body' => 'At it']),
        dryAction('a10', 'set_reminder', ['after' => ['amount' => 2, 'unit' => 'hours'], 'note' => 'Check']),
        dryAction('a11', 'send_webhook', ['method' => 'POST', 'url' => 'https://hooks.example.com/{{ ticket.number }}', 'body' => '{"n": {{ ticket.number }}}']),
    ];
    $counts = fn () => [
        AutomationRun::query()->count(), TicketEvent::query()->count(), TicketComment::query()->count(),
        DatabaseNotification::query()->count(), Ticket::query()->count(), Automation::query()->count(),
    ];
    $before = $counts();
    $ticketBefore = $this->ticket->fresh()->toArray();

    actAs($this->admin, $this->workspace);
    $response = $this->postJson($this->url, ['definition' => dryDefinition($steps), 'ticket_id' => $this->ticket->id])
        ->assertOk()
        ->assertJsonPath('matched', true)
        ->assertJsonPath('error', null);

    $log = collect($response->json('log'));
    expect($counts())->toBe($before)
        ->and($this->ticket->fresh()->toArray())->toBe($ticketBefore)
        ->and($log->pluck('step_id')->all())->toBe(array_column($steps, 'id'))
        ->and($log->every(fn (array $entry) => str_starts_with($entry['result'], 'Would')))->toBeTrue()
        ->and($log->firstWhere('step_id', 'a6')['result'])->toContain('Note for '.$this->ticket->requester->name)
        ->and($log->firstWhere('step_id', 'a11')['result'])->toContain("https://hooks.example.com/{$this->ticket->number}")
        ->and(RateLimiter::attempts("automations:ticket:{$this->ticket->id}"))->toBe(0)
        ->and(Cache::has('automation-failure-notice:'.$this->ticket->id))->toBeFalse();

    Queue::assertNothingPushed();
    Bus::assertNothingDispatched();
    Notification::assertNothingSent();
});

test('the trigger is evaluated against the chosen event of the ticket only', function () {
    $created = $this->ticket->recordEvent(TicketEventType::CREATED, null);
    $deleted = $this->ticket->recordEvent(TicketEventType::DELETED, $this->admin);
    $foreign = Ticket::factory()->for($this->desk)->create()->recordEvent(TicketEventType::CREATED, null);
    $body = fn (?TicketEvent $event) => ['definition' => dryDefinition([dryAction('a1', 'add_note', ['body' => 'x'])]), 'ticket_id' => $this->ticket->id, 'ticket_event_id' => $event?->id];
    actAs($this->admin, $this->workspace);

    $this->postJson($this->url, $body($created))->assertOk()->assertJsonPath('trigger_matched', true);
    $this->postJson($this->url, $body($deleted))->assertOk()->assertJsonPath('trigger_matched', false)->assertJsonPath('trigger_note', null);
    $this->postJson($this->url, $body(null))->assertOk()->assertJsonPath('trigger_matched', null)->assertJsonPath('trigger_note', fn ($note) => str_contains($note, 'No event'));
    $this->postJson($this->url, $body($foreign))->assertUnprocessable()->assertJsonValidationErrors('ticket_event_id');

    $webhook = ['mode' => 'find', 'match_by' => 'number', 'path' => 'ticket'];
    $this->postJson($this->url, ['definition' => dryDefinition([], 'webhook', $webhook), 'ticket_id' => $this->ticket->id, 'ticket_event_id' => $created->id])
        ->assertOk()->assertJsonPath('trigger_matched', null)->assertJsonPath('trigger_note', fn ($note) => str_contains($note, 'not start from a ticket event'));
});

test('conditions, paths, delays and stops are evaluated against the ticket as it is', function () {
    actAs($this->admin, $this->workspace);
    $paths = ['id' => 'p', 'type' => 'paths', 'branches' => [
        ['name' => 'Normal', 'condition' => ['match' => 'all', 'rules' => dryCondition('x', 'normal')['rules']], 'steps' => [dryAction('b1', 'add_note', ['body' => 'normal branch'])]],
    ], 'else' => [dryAction('e1', 'add_note', ['body' => 'else'])]];
    $delay = ['id' => 'd', 'type' => 'delay', 'for' => ['amount' => 1, 'unit' => 'days']];
    $run = fn (array $steps) => $this->postJson($this->url, ['definition' => dryDefinition($steps), 'ticket_id' => $this->ticket->id])->assertOk();

    $failed = $run([dryCondition('c', 'urgent'), dryAction('a1', 'add_note', ['body' => 'never'])]);
    expect($failed->json('matched'))->toBeFalse()->and(array_column($failed->json('log'), 'step_id'))->toBe(['c']);

    $branched = $run([$paths, $delay, dryAction('a2', 'add_note', ['body' => 'after'])]);
    $log = collect($branched->json('log'));
    expect($branched->json('matched'))->toBeTrue()
        ->and($log->pluck('step_id')->all())->toBe(['p', 'b1', 'd', 'a2'])
        ->and($log->firstWhere('step_id', 'p')['branch'])->toBe('Normal')
        ->and($log->firstWhere('step_id', 'd'))->toMatchArray(['waiting' => true])->toHaveKeys(['resume_at', 'note']);

    $stopped = $run([['id' => 's', 'type' => 'stop'], dryAction('a3', 'add_note', ['body' => 'never'])]);
    expect($stopped->json('stopped'))->toBeTrue()->and(array_column($stopped->json('log'), 'step_id'))->toBe(['s']);
});

test('a step that errors is captured with a safe message', function () {
    actAs($this->admin, $this->workspace);
    $delay = ['id' => 'd', 'type' => 'delay', 'until' => ['field' => 'ticket.due_at', 'offset' => 0]];

    $this->postJson($this->url, ['definition' => dryDefinition([$delay]), 'ticket_id' => $this->ticket->id])
        ->assertOk()->assertJsonPath('error', 'The delay can not wait until a date that is not set.')
        ->assertJsonPath('log.0.error', 'The delay can not wait until a date that is not set.');
});

test('an invalid definition is reported by step and never runs', function () {
    actAs($this->admin, $this->workspace);

    $this->postJson($this->url, ['definition' => dryDefinition([dryAction('bad', 'set_field', ['field' => 'ticket.priority', 'value' => 'nope'])]), 'ticket_id' => $this->ticket->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.bad.config.value']);
    $this->postJson($this->url, ['definition' => dryDefinition([dryAction('n', 'add_note', ['body' => 'x'])]), 'ticket_id' => 'tkt_missing'])
        ->assertUnprocessable()->assertJsonValidationErrors(['ticket_id']);
});

test('the sample payload fills webhook placeholders and payload conditions', function () {
    actAs($this->admin, $this->workspace);
    $condition = ['id' => 'c', 'type' => 'condition', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => 'webhook.payload_number.order.total', 'operator' => 'gt', 'value' => 100]]];
    $definition = dryDefinition([$condition, dryAction('n', 'add_note', ['body' => 'Order {{ webhook.payload.order.id }}'])], 'webhook', ['mode' => 'find', 'match_by' => 'number', 'path' => 'ticket']);

    $this->postJson($this->url, ['definition' => $definition, 'ticket_id' => $this->ticket->id, 'sample_payload' => ['order' => ['id' => 'A-9', 'total' => 150]]])
        ->assertOk()->assertJsonPath('matched', true)->assertJsonPath('log.1.result', 'Would add the internal note: Order A-9');
    $this->postJson($this->url, ['definition' => $definition, 'ticket_id' => $this->ticket->id, 'sample_payload' => ['order' => ['id' => 'A-9', 'total' => 5]]])
        ->assertOk()->assertJsonPath('matched', false);
    $this->postJson($this->url, ['definition' => $definition, 'ticket_id' => $this->ticket->id, 'sample_payload' => ['big' => str_repeat('x', 70000)]])
        ->assertUnprocessable()->assertJsonValidationErrors('sample_payload');
});

test('who may test: admins any ticket, leads their desk scope and tickets, agents never', function () {
    $otherDesk = Desk::factory()->create();
    $elsewhere = Ticket::factory()->for($otherDesk)->create();
    $definition = dryDefinition([dryAction('n', 'add_note', ['body' => 'x'])]);
    $body = fn (Ticket $ticket, ?Desk $desk) => ['definition' => $definition, 'ticket_id' => $ticket->id, 'desk_id' => $desk?->id];

    actAs($this->admin, $this->workspace);
    $this->postJson($this->url, $body($elsewhere, null))->assertOk();
    $this->postJson($this->url, $body($elsewhere, $this->desk))->assertUnprocessable()->assertJsonValidationErrors('ticket_id');

    actAs($this->lead, $this->workspace);
    $this->postJson($this->url, $body($this->ticket, $this->desk))->assertOk();
    $this->postJson($this->url, $body($this->ticket, null))->assertForbidden();
    $this->postJson($this->url, $body($this->ticket, $otherDesk))->assertForbidden();
    $this->postJson($this->url, $body($elsewhere, $this->desk))->assertForbidden();

    actAs($this->agent, $this->workspace);
    $this->postJson($this->url, $body($this->ticket, $this->desk))->assertForbidden();
});
