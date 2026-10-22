<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\AutomationTicketMatch;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00:00 UTC'));

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->desk = Desk::sole();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
});

/** Daily at 09:00 New York, which is 13:00 UTC in October. */
const DAILY = ['frequency' => 'daily', 'time' => '09:00', 'timezone' => 'America/New_York', 'refire_after_days' => null];

/**
 * @param  list<array<string, mixed>>|null  $steps  Defaults to: if the ticket is open, make it urgent.
 * @return array<string, mixed>
 */
function scheduleDefinition(array $config = [], ?array $steps = null): array
{
    return [
        'trigger' => ['type' => 'schedule', 'config' => [...DAILY, ...$config]],
        'steps' => $steps ?? [
            ['id' => 'c', 'type' => 'condition', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => 'ticket.status', 'operator' => 'is', 'value' => 'open']]],
            ['id' => 'a', 'type' => 'action', 'action' => 'set_field', 'config' => ['field' => 'ticket.priority', 'value' => 'urgent']],
        ],
    ];
}

/**
 * An enabled schedule automation that was last swept just now, so it only fires for later moments.
 *
 * @param  array<string, mixed>  $attributes
 */
function scheduled(array $config = [], array $attributes = [], ?array $steps = null): Automation
{
    return Automation::factory()->enabled()->create([
        'trigger_type' => 'schedule',
        'definition' => scheduleDefinition($config, $steps),
        'last_swept_at' => now(),
        ...$attributes,
    ]);
}

function sweep(): void
{
    test()->artisan('automations:sweep')->assertSuccessful();
}

function runCount(?Automation $automation = null, ?Ticket $ticket = null): int
{
    return AutomationRun::query()
        ->when($automation, fn ($query) => $query->where('automation_id', $automation->id))
        ->when($ticket, fn ($query) => $query->where('ticket_id', $ticket->id))
        ->count();
}

test('a schedule automation is saved with a valid config and rejected with a bad one', function () {
    actAs($this->admin, $this->workspace);
    $url = 'http://acme.harbor.test/api/v1/automations';
    $post = fn (array $config = [], ?array $steps = null) => $this->postJson($url, ['name' => 'Sweep', 'definition' => scheduleDefinition($config, $steps)]);

    $post()->assertCreated();
    $post(['frequency' => 'hourly', 'time' => '00:15', 'refire_after_days' => 3])->assertCreated();
    $post(['timezone' => 'Mars/Olympus'])->assertJsonValidationErrors('definition.trigger.config.timezone');
    $post(['time' => '25:99'])->assertJsonValidationErrors('definition.trigger.config.time');
    $post(['frequency' => 'weekly'])->assertJsonValidationErrors('definition.trigger.config.frequency');
    $post(['refire_after_days' => 0])->assertJsonValidationErrors('definition.trigger.config.refire_after_days');

    foreach (['message.body' => 'contains', 'event.field' => 'is'] as $field => $operator) {
        $rule = ['id' => 'c', 'type' => 'condition', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => $field, 'operator' => $operator, 'value' => 'x']]];
        $post(steps: [$rule])->assertJsonValidationErrors('definition.steps.c.rules.0.field');
    }

    $changed = ['id' => 'c', 'type' => 'condition', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => 'ticket.status', 'operator' => 'changed']]];
    $post(steps: [$changed])->assertJsonValidationErrors('definition.steps.c.rules.0.operator');
});

test('the catalog lists the schedule trigger', function () {
    actAs($this->admin, $this->workspace);

    $trigger = collect($this->getJson('http://acme.harbor.test/api/v1/automations/catalog')->assertOk()->json('triggers'))->firstWhere('key', 'schedule');

    expect($trigger)->not->toBeNull()->and($trigger['field_groups'])->toBe([]);
});

test('a daily sweep waits for its time in its timezone, then fires once per day for tickets past the refire window', function () {
    $automation = scheduled(['refire_after_days' => 2]);
    $first = Ticket::factory()->create();

    sweep();
    expect(runCount())->toBe(0);

    $this->travelTo(CarbonImmutable::parse('2026-10-21 12:59:00 UTC'));
    sweep();
    expect(runCount())->toBe(0);

    $this->travelTo(CarbonImmutable::parse('2026-10-21 13:00:00 UTC'));
    sweep();
    sweep();
    expect(runCount($automation, $first))->toBe(1)
        ->and($first->refresh()->priority)->toBe(TicketPriority::URGENT);

    $this->travelTo(CarbonImmutable::parse('2026-10-21 18:00:00 UTC'));
    $second = Ticket::factory()->create();
    sweep();
    expect(runCount())->toBe(1);

    $this->travelTo(CarbonImmutable::parse('2026-10-22 13:00:00 UTC'));
    sweep();
    expect(runCount($automation, $first))->toBe(1)->and(runCount($automation, $second))->toBe(1);

    $this->travelTo(CarbonImmutable::parse('2026-10-23 13:00:00 UTC'));
    sweep();
    expect(runCount($automation, $first))->toBe(2)->and(runCount($automation, $second))->toBe(1);
});

test('without refire_after_days a ticket fires once across many sweeps', function () {
    $automation = scheduled();
    $ticket = Ticket::factory()->create();

    foreach (range(1, 4) as $day) {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 13:00:00 UTC')->addDays($day));
        sweep();
    }

    expect(runCount($automation, $ticket))->toBe(1)
        ->and(AutomationTicketMatch::query()->where('ticket_id', $ticket->id)->count())->toBe(1);
});

test('an hourly sweep fires at the configured minute of each hour', function () {
    $automation = scheduled(['frequency' => 'hourly', 'time' => '00:30']);
    $early = Ticket::factory()->create();

    $this->travelTo(CarbonImmutable::parse('2026-10-21 10:29:00 UTC'));
    sweep();
    expect(runCount())->toBe(0);

    $this->travelTo(CarbonImmutable::parse('2026-10-21 10:30:00 UTC'));
    sweep();
    expect(runCount($automation, $early))->toBe(1)
        ->and($automation->refresh()->last_swept_at->toIso8601String())->toBe('2026-10-21T10:30:00+00:00');

    $this->travelTo(CarbonImmutable::parse('2026-10-21 11:00:00 UTC'));
    $late = Ticket::factory()->create();
    sweep();
    expect(runCount($automation, $late))->toBe(0);

    $this->travelTo(CarbonImmutable::parse('2026-10-21 11:31:00 UTC'));
    sweep();
    expect(runCount($automation, $late))->toBe(1)->and(runCount($automation, $early))->toBe(1)
        ->and($automation->refresh()->last_swept_at->toIso8601String())->toBe('2026-10-21T11:30:00+00:00');
});

test('a missed tick sweeps once for the latest moment, and an automation never swept starts with its latest moment', function () {
    $automation = scheduled(['refire_after_days' => 1]);
    $ticket = Ticket::factory()->create();

    $this->travelTo(CarbonImmutable::parse('2026-10-25 15:00:00 UTC'));
    sweep();

    expect(runCount($automation))->toBe(1)
        ->and($automation->refresh()->last_swept_at->toIso8601String())->toBe('2026-10-25T13:00:00+00:00');

    $never = scheduled(attributes: ['last_swept_at' => null]);
    sweep();
    expect(runCount($never, $ticket))->toBe(1)->and($never->refresh()->last_swept_at->toIso8601String())->toBe('2026-10-25T13:00:00+00:00');
});

test('a new, edited or re-enabled automation does not fire for a moment that passed before the change', function () {
    actAs($this->admin, $this->workspace);
    Ticket::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-21 14:00:00 UTC'));

    $id = $this->postJson('http://acme.harbor.test/api/v1/automations', ['name' => 'Sweep', 'enabled' => true, 'definition' => scheduleDefinition()])->assertCreated()->json('id');
    sweep();
    expect(runCount())->toBe(0);

    $this->putJson("http://acme.harbor.test/api/v1/automations/$id", ['definition' => scheduleDefinition(['time' => '13:30'])])->assertOk();
    $this->putJson("http://acme.harbor.test/api/v1/automations/$id/enabled", ['enabled' => false])->assertOk();
    $this->travelTo(CarbonImmutable::parse('2026-10-21 16:00:00 UTC'));
    $this->putJson("http://acme.harbor.test/api/v1/automations/$id/enabled", ['enabled' => true])->assertOk();
    sweep();

    expect(runCount())->toBe(0);
});

test('only matching tickets in scope get a run', function () {
    $otherDesk = Desk::factory()->create();
    $scoped = scheduled(attributes: ['desk_id' => $this->desk->id], steps: [
        ['id' => 'c', 'type' => 'condition', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => 'ticket.priority', 'operator' => 'is', 'value' => 'normal']]],
        ['id' => 'a', 'type' => 'action', 'action' => 'set_field', 'config' => ['field' => 'ticket.priority', 'value' => 'high']],
    ]);
    $disabled = scheduled(attributes: ['enabled' => false]);

    $match = Ticket::factory()->create(['desk_id' => $this->desk->id, 'priority' => TicketPriority::NORMAL]);
    $noMatch = Ticket::factory()->create(['desk_id' => $this->desk->id, 'priority' => TicketPriority::LOW]);
    $closed = Ticket::factory()->create(['desk_id' => $this->desk->id, 'priority' => TicketPriority::NORMAL, 'status' => TicketStatus::CLOSED]);
    $deleted = Ticket::factory()->create(['desk_id' => $this->desk->id, 'priority' => TicketPriority::NORMAL]);
    $deleted->delete();
    $elsewhere = Ticket::factory()->create(['desk_id' => $otherDesk->id, 'priority' => TicketPriority::NORMAL]);

    $this->travelTo(CarbonImmutable::parse('2026-10-21 13:00:00 UTC'));
    sweep();

    expect(AutomationRun::query()->pluck('ticket_id')->all())->toBe([$match->id])
        ->and(runCount($disabled))->toBe(0)
        ->and($match->refresh()->priority)->toBe(TicketPriority::HIGH)
        ->and(AutomationTicketMatch::query()->pluck('ticket_id')->all())->toBe([$match->id])
        ->and($noMatch->refresh()->priority)->toBe(TicketPriority::LOW)
        ->and($closed->refresh()->priority)->toBe(TicketPriority::NORMAL)
        ->and($elsewhere->refresh()->priority)->toBe(TicketPriority::NORMAL);
});

test('concurrent sweeps do not double-fire', function () {
    $automation = scheduled();
    $ticket = Ticket::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-21 13:00:00 UTC'));

    sweep();
    sweep();
    expect(runCount($automation, $ticket))->toBe(1);

    // Even a sweep that claims the same moment again finds the ticket's match.
    $automation->forceFill(['last_swept_at' => null])->save();
    $ticket->refresh()->update(['priority' => TicketPriority::NORMAL]);
    sweep();
    expect(runCount($automation, $ticket))->toBe(1)->and($ticket->refresh()->priority)->toBe(TicketPriority::NORMAL);
});

test('a scheduled run closes stale tickets as the automation, and its delays resume', function () {
    $automation = scheduled(steps: [
        ['id' => 'c', 'type' => 'condition', 'match' => 'all', 'rules' => [
            ['type' => 'rule', 'field' => 'ticket.status', 'operator' => 'is', 'value' => 'solved'],
            ['type' => 'rule', 'field' => 'time.since_updated', 'operator' => 'gt', 'value' => ['amount' => 3, 'unit' => 'days']],
        ]],
        ['id' => 'd', 'type' => 'delay', 'cancel_on' => [], 'for' => ['amount' => 1, 'unit' => 'hours']],
        ['id' => 'a', 'type' => 'action', 'action' => 'set_field', 'config' => ['field' => 'ticket.status', 'value' => 'closed']],
    ]);
    $stale = Ticket::factory()->create(['status' => TicketStatus::SOLVED, 'updated_at' => now()->subDays(5)]);
    $fresh = Ticket::factory()->create(['status' => TicketStatus::SOLVED, 'updated_at' => now()->subDay()]);

    $this->travelTo(CarbonImmutable::parse('2026-10-21 13:00:00 UTC'));
    sweep();

    $run = AutomationRun::query()->sole();
    expect($run->ticket_event_id)->toBeNull()->and($run->depth)->toBe(0)->and($run->status->value)->toBe('waiting');

    $this->travel(61)->minutes();
    $this->artisan('automations:resume')->assertSuccessful();

    $event = $stale->refresh()->events()->where('type', 'status_changed')->latest('id')->firstOrFail();
    expect($stale->status)->toBe(TicketStatus::CLOSED)
        ->and($fresh->refresh()->status)->toBe(TicketStatus::SOLVED)
        ->and($run->refresh()->status->value)->toBe('completed')
        ->and($event->actor_type)->toBe('automation')->and($event->actor_id)->toBe($automation->id);
});

test('a workspace sweeps and changes only its own tickets', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $ticketA = Ticket::factory()->create();
    $ticketB = $other->execute(fn () => Ticket::factory()->create());
    $automationA = scheduled();

    $this->travelTo(CarbonImmutable::parse('2026-10-21 13:00:00 UTC'));
    sweep();

    $other->execute(function () use ($ticketB) {
        expect($ticketB->refresh()->priority)->not->toBe(TicketPriority::URGENT)
            ->and(AutomationRun::query()->count())->toBe(0)
            ->and(AutomationTicketMatch::query()->count())->toBe(0);
    });
    expect($ticketA->refresh()->priority)->toBe(TicketPriority::URGENT);

    // Workspace B gets an identical automation of its own.
    $automationB = $other->execute(fn () => scheduled(attributes: ['last_swept_at' => now()->subDay()]));
    $ticketA->refresh()->update(['priority' => TicketPriority::NORMAL]);
    sweep();

    $other->execute(function () use ($ticketB, $automationB, $other) {
        expect($ticketB->refresh()->priority)->toBe(TicketPriority::URGENT)
            ->and(AutomationRun::query()->pluck('automation_id')->all())->toBe([$automationB->id])
            ->and(AutomationRun::query()->pluck('ticket_id')->all())->toBe([$ticketB->id])
            ->and(AutomationTicketMatch::query()->sole()->workspace_id)->toBe($other->id);
    });

    expect(AutomationRun::query()->pluck('automation_id')->unique()->all())->toBe([$automationA->id])
        ->and(AutomationTicketMatch::query()->sole()->workspace_id)->toBe($this->workspace->id)
        ->and($ticketA->refresh()->priority)->toBe(TicketPriority::NORMAL);
});
