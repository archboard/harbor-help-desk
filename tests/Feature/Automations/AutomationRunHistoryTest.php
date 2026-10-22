<?php

use App\Enums\AutomationRunStatus;
use App\Enums\DeskRole;
use App\Enums\TicketEventType;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->desk = Desk::sole();
    $this->otherDesk = Desk::factory()->create();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD);
    $this->agent = deskMember($this->workspace, $this->desk);
    $this->automation = Automation::factory()->forDesk($this->desk)->create();
    $this->wide = Automation::factory()->create();
    $this->ticket = Ticket::factory()->for($this->desk)->create();
    $this->base = 'http://acme.harbor.test';
});

/**
 * @param  array<string, mixed>  $attributes
 */
function makeRun(Automation $automation, Ticket $ticket, array $attributes = []): AutomationRun
{
    return AutomationRun::query()->create([
        'workspace_id' => $automation->workspace_id,
        'automation_id' => $automation->id,
        'ticket_id' => $ticket->id,
        'depth' => 0,
        'status' => AutomationRunStatus::COMPLETED,
        'definition' => $automation->definition,
        'log' => [['step_id' => 's1', 'type' => 'stop']],
        'state' => ['payload' => ['secret' => 'hook']],
        ...$attributes,
    ]);
}

test('the run list is newest first, paginated and leaves out the snapshot and payload', function () {
    $old = makeRun($this->automation, $this->ticket, ['created_at' => now()->subDays(3)]);
    $new = makeRun($this->automation, $this->ticket, ['created_at' => now()]);
    makeRun($this->wide, $this->ticket);
    actAs($this->admin, $this->workspace);

    $response = $this->getJson("{$this->base}/api/v1/automations/{$this->automation->id}/runs")
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total']])
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id)
        ->assertJsonPath('data.0.automation_name', $this->automation->name)
        ->assertJsonPath('data.0.ticket_number', $this->ticket->number)
        ->assertJsonPath('data.0.log.0.step_id', 's1');

    expect($response->json('data.0'))->not->toHaveKeys(['definition', 'payload', 'state']);
});

test('the run list filters by status, ticket and date', function () {
    $other = Ticket::factory()->for($this->desk)->create();
    $failed = makeRun($this->automation, $this->ticket, ['status' => AutomationRunStatus::FAILED, 'created_at' => now()->subDays(10)]);
    $onOther = makeRun($this->automation, $other, ['created_at' => now()->subDays(5)]);
    $recent = makeRun($this->automation, $this->ticket, ['created_at' => now()]);
    actAs($this->admin, $this->workspace);
    $ids = fn (string $query) => collect($this->getJson("{$this->base}/api/v1/automations/{$this->automation->id}/runs?$query")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('status=failed'))->toBe([$failed->id])
        ->and($ids("ticket_id={$other->id}"))->toBe([$onOther->id])
        ->and($ids('from='.now()->subDays(6)->toDateString()))->toBe([$recent->id, $onOther->id])
        ->and($ids('to='.now()->subDays(5)->toDateString()))->toBe([$onOther->id, $failed->id]);

    $this->getJson("{$this->base}/api/v1/automations/{$this->automation->id}/runs?status=bogus&from=2026-02-01&to=2026-01-01")
        ->assertUnprocessable()->assertJsonValidationErrors(['status', 'to']);
});

test('the run detail shows the log, the definition snapshot and the payload', function () {
    $run = makeRun($this->automation, $this->ticket);

    foreach (["{$this->base}/api/v1/automation-runs/{$run->id}", "{$this->base}/automation-runs/{$run->id}"] as $url) {
        actAs($this->admin, $this->workspace);
        $this->actingAs($this->admin)->getJson($url)
            ->assertOk()
            ->assertJsonPath('id', $run->id)
            ->assertJsonPath('log.0.step_id', 's1')
            ->assertJsonPath('payload.secret', 'hook')
            ->assertJsonPath('definition.trigger.type', 'ticket.created')
            ->assertJsonPath('ticket_subject', $this->ticket->subject);
    }

    $this->actingAs($this->admin)->getJson("{$this->base}/automations/{$this->automation->id}/runs")->assertOk()->assertJsonPath('data.0.id', $run->id);
});

test('run history follows who may view the automation', function () {
    $own = makeRun($this->automation, $this->ticket);
    $wide = makeRun($this->wide, $this->ticket);
    $foreignDesk = Automation::factory()->forDesk($this->otherDesk)->create();
    $foreign = makeRun($foreignDesk, Ticket::factory()->for($this->otherDesk)->create());

    actAs($this->lead, $this->workspace);
    $this->getJson("{$this->base}/api/v1/automations/{$this->automation->id}/runs")->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson("{$this->base}/api/v1/automation-runs/{$own->id}")->assertOk();
    $this->getJson("{$this->base}/api/v1/automations/{$this->wide->id}/runs")->assertForbidden();
    $this->getJson("{$this->base}/api/v1/automations/{$foreignDesk->id}/runs")->assertForbidden();
    $this->getJson("{$this->base}/api/v1/automation-runs/{$wide->id}")->assertForbidden();
    $this->getJson("{$this->base}/api/v1/automation-runs/{$foreign->id}")->assertForbidden();

    actAs($this->agent, $this->workspace);
    $this->getJson("{$this->base}/api/v1/automations/{$this->automation->id}/runs")->assertForbidden();
    $this->getJson("{$this->base}/api/v1/automation-runs/{$own->id}")->assertForbidden();
    $this->actingAs($this->agent)->getJson("{$this->base}/automation-runs/{$own->id}")->assertForbidden();
});

test('a ticket event caused by an automation exposes the run and the automation as actor', function () {
    $automation = Automation::factory()->enabled()->triggeredBy('ticket.created', 'set_field', ['field' => 'ticket.priority', 'value' => 'urgent'])->create(['name' => 'Greeter']);
    $this->ticket->recordEvent(TicketEventType::CREATED, null);
    $run = AutomationRun::sole();

    actAs($this->agent, $this->workspace);
    $events = collect($this->getJson("{$this->base}/api/v1/tickets/{$this->ticket->id}/events")->assertOk()->json('data'));
    $caused = $events->firstWhere('automation_run_id', $run->id);

    expect($caused)->toMatchArray(['actor_type' => 'automation', 'actor_name' => 'Greeter', 'actor_id' => $automation->id])
        ->and($events->firstWhere('type', 'created')['automation_run_id'])->toBeNull();

    $this->actingAs($this->agent)->get("{$this->base}/tickets/{$this->ticket->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('events', fn ($shown) => collect($shown)->contains(
            fn ($event) => $event['automation_run_id'] === $run->id && $event['actor_name'] === 'Greeter' && $event['actor_type'] === 'automation',
        )));
});
