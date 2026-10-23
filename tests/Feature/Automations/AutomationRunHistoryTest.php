<?php

use App\Enums\AutomationRunStatus;
use App\Enums\DeskRole;
use App\Enums\TicketEventType;
use App\Enums\WorkspaceRole;
use App\Events\AutomationRunChanged;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;
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

    actAs($this->admin, $this->workspace);
    $this->getJson("{$this->base}/api/v1/automation-runs/{$run->id}")
        ->assertOk()
        ->assertJsonPath('id', $run->id)
        ->assertJsonPath('log.0.step_id', 's1')
        ->assertJsonPath('payload.secret', 'hook')
        ->assertJsonPath('definition.trigger.type', 'ticket.created')
        ->assertJsonPath('ticket_subject', $this->ticket->subject);

    $this->actingAs($this->admin)->get("{$this->base}/automation-runs/{$run->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('automations/Run')
        ->where('run.id', $run->id)
        ->where('run.log.0.step_id', 's1')
        ->where('run.payload.secret', 'hook')
        ->where('run.definition.trigger.type', 'ticket.created')
        ->where('canCancel', true)
        ->where('event', null)
        ->has('desks'));
});

test('the history page lists an automation\'s runs and filters them', function () {
    $other = Ticket::factory()->for($this->desk)->create();
    $failed = makeRun($this->automation, $this->ticket, ['status' => AutomationRunStatus::FAILED]);
    $onOther = makeRun($this->automation, $other);
    makeRun($this->wide, $this->ticket);
    $url = "{$this->base}/automations/{$this->automation->id}/runs";

    $this->actingAs($this->admin)->get($url)->assertInertia(fn (AssertableInertia $page) => $page
        ->component('automations/Runs')
        ->where('automation.id', $this->automation->id)
        ->has('runs.data', 2)
        ->has('statuses'));
    $this->get("$url?status=failed")->assertInertia(fn (AssertableInertia $page) => $page
        ->has('runs.data', 1)
        ->where('runs.data.0.id', $failed->id)
        ->where('filters.status', 'failed'));
    $this->get("$url?ticket_number={$other->number}")->assertInertia(fn (AssertableInertia $page) => $page
        ->has('runs.data', 1)
        ->where('runs.data.0.id', $onOther->id));
});

test('the workspace-wide page shows the runs the user may view', function () {
    makeRun($this->automation, $this->ticket);
    makeRun($this->wide, $this->ticket);
    makeRun(Automation::factory()->forDesk($this->otherDesk)->create(), Ticket::factory()->for($this->otherDesk)->create());

    $this->actingAs($this->admin)->get("{$this->base}/automations/runs")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('automations/Runs')
        ->where('automation', null)
        ->has('runs.data', 3)
        ->where('runs.data.0.automation_name', fn ($name) => is_string($name)));

    $this->actingAs($this->lead)->get("{$this->base}/automations/runs")->assertInertia(fn (AssertableInertia $page) => $page
        ->has('runs.data', 1)
        ->where('runs.data.0.automation_id', $this->automation->id));

    $this->actingAs($this->agent)->get("{$this->base}/automations/runs")->assertForbidden();
    $this->actingAs($this->agent)->get("{$this->base}/automations/{$this->automation->id}/runs")->assertForbidden();
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

    $this->actingAs($this->lead)->get("{$this->base}/automation-runs/{$wide->id}")->assertForbidden();
    $this->get("{$this->base}/automations/{$this->wide->id}/runs")->assertForbidden();

    actAs($this->agent, $this->workspace);
    $this->getJson("{$this->base}/api/v1/automations/{$this->automation->id}/runs")->assertForbidden();
    $this->getJson("{$this->base}/api/v1/automation-runs/{$own->id}")->assertForbidden();
    $this->actingAs($this->agent)->get("{$this->base}/automation-runs/{$own->id}")->assertForbidden();
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

test('a waiting run can be cancelled by someone who may edit its automation, over the web and the API', function () {
    $web = makeRun($this->automation, $this->ticket, ['status' => AutomationRunStatus::WAITING, 'resume_at' => now()->addDay()]);
    $api = makeRun($this->automation, $this->ticket, ['status' => AutomationRunStatus::WAITING, 'resume_at' => now()->addDay()]);

    $this->actingAs($this->lead)->post("{$this->base}/automation-runs/{$web->id}/cancel")->assertRedirect();
    expect($web->refresh())->status->toBe(AutomationRunStatus::CANCELLED)
        ->resume_at->toBeNull()
        ->and($web->log[1]['reason'])->toBe("Cancelled by {$this->lead->name}");

    actAs($this->admin, $this->workspace);
    $this->postJson("{$this->base}/api/v1/automation-runs/{$api->id}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
});

test('only a waiting run can be cancelled', function () {
    $done = makeRun($this->automation, $this->ticket);

    $this->actingAs($this->admin)->postJson("{$this->base}/automation-runs/{$done->id}/cancel")->assertUnprocessable()->assertJsonValidationErrors('run');
    expect($done->refresh()->status)->toBe(AutomationRunStatus::COMPLETED);
});

test('agents and leads of other desks can not cancel a run', function () {
    $waiting = fn (Automation $automation) => makeRun($automation, $this->ticket, ['status' => AutomationRunStatus::WAITING, 'resume_at' => now()->addDay()]);
    $own = $waiting($this->automation);
    $wide = $waiting($this->wide);

    $this->actingAs($this->agent)->post("{$this->base}/automation-runs/{$own->id}/cancel")->assertForbidden();
    $this->actingAs($this->lead)->post("{$this->base}/automation-runs/{$wide->id}/cancel")->assertForbidden();
    expect($own->refresh()->status)->toBe(AutomationRunStatus::WAITING)
        ->and($wide->refresh()->status)->toBe(AutomationRunStatus::WAITING);

    actAs($this->agent, $this->workspace);
    $this->postJson("{$this->base}/api/v1/automation-runs/{$own->id}/cancel")->assertForbidden();
});

test('a run that finishes or starts waiting pings its automation\'s channel with ids only', function () {
    Event::fake([AutomationRunChanged::class]);

    $run = makeRun($this->automation, $this->ticket, ['status' => AutomationRunStatus::RUNNING]);
    Event::assertNotDispatched(AutomationRunChanged::class);

    $run->update(['status' => AutomationRunStatus::WAITING]);
    $run->update(['log' => []]);
    $run->update(['status' => AutomationRunStatus::COMPLETED]);

    Event::assertDispatchedTimes(AutomationRunChanged::class, 2);
    Event::assertDispatched(AutomationRunChanged::class, function (AutomationRunChanged $event) use ($run): bool {
        expect($event->broadcastOn()[0]->name)->toBe("private-automations.{$this->automation->id}")
            ->and($event->broadcastWith())->toBe(['automation_id' => $this->automation->id, 'run_id' => $run->id, 'status' => $event->status->value]);

        return true;
    });
});
