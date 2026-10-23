<?php

use App\Enums\AutomationRunStatus;
use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Desk;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

function runFor(Automation $automation, AutomationRunStatus $status, string $createdAt = 'now'): AutomationRun
{
    $run = AutomationRun::query()->create([
        'workspace_id' => $automation->workspace_id, 'automation_id' => $automation->id, 'depth' => 0,
        'status' => $status, 'definition' => $automation->definition, 'log' => [], 'state' => [],
    ]);
    $run->forceFill(['created_at' => $createdAt])->save();

    return $run;
}

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->desk = Desk::sole();
    $this->lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD);
    $this->agent = deskMember($this->workspace, $this->desk);
});

test('an admin sees every automation with its last run and the runs of the last 24 hours', function () {
    $wide = Automation::factory()->create(['name' => 'Wide']);
    Automation::factory()->forDesk($this->desk)->create(['name' => 'Scoped', 'position' => 2]);
    runFor($wide, AutomationRunStatus::FAILED, '-3 days');
    runFor($wide, AutomationRunStatus::COMPLETED, '-2 hours');
    $latest = runFor($wide, AutomationRunStatus::SKIPPED, '-1 hour');

    $this->actingAs($this->admin)->get(route('automations.index'))->assertInertia(fn (Assert $page) => $page
        ->component('automations/Index')
        ->has('automations', 2)
        ->where('automations.0.name', 'Wide')
        ->where('automations.0.runs_last24h', 2)
        ->where('automations.0.last_run.id', $latest->id)
        ->where('automations.0.last_run.status', 'skipped')
        ->missing('automations.0.last_run.log')
        ->where('automations.1.last_run', null)
        ->where('automations.1.runs_last24h', 0)
        ->has('desks', 1)
        ->has('triggers'));
});

test('a desk lead sees only the automations of the desks they lead', function () {
    Automation::factory()->create(['name' => 'Wide']);
    Automation::factory()->forDesk($this->desk)->create(['name' => 'Mine']);

    $this->actingAs($this->lead)->get(route('automations.index'))->assertInertia(fn (Assert $page) => $page
        ->has('automations', 1)
        ->where('automations.0.name', 'Mine'));
});

test('an agent can not open the page', function () {
    $this->actingAs($this->agent)->get(route('automations.index'))->assertForbidden();
});

test('only people who can view automations get the nav flag', function () {
    $this->actingAs($this->admin)->get(route('automations.index'))->assertInertia(fn (Assert $page) => $page->where('canViewAutomations', true));
    $this->actingAs($this->agent)->get(route('tickets.index'))->assertInertia(fn (Assert $page) => $page->where('canViewAutomations', false));
});

test('an automation can be switched on and off', function () {
    $automation = Automation::factory()->create();

    $this->actingAs($this->admin)->put(route('automations.enabled.update', $automation), ['enabled' => true])->assertRedirect();
    expect($automation->refresh()->enabled)->toBeTrue();

    $this->put(route('automations.enabled.update', $automation), ['enabled' => false])->assertRedirect();
    expect($automation->refresh()->enabled)->toBeFalse();
});

test('an automation can be moved within its trigger', function () {
    $first = Automation::factory()->create(['position' => 1]);
    $second = Automation::factory()->create(['position' => 2]);

    $this->actingAs($this->admin)->post(route('automations.reorder'), ['ids' => [$second->id, $first->id]])->assertRedirect();

    expect($second->refresh()->position)->toBe(1)->and($first->refresh()->position)->toBe(2);
});

test('a duplicate is a disabled copy placed last in its trigger', function () {
    $source = Automation::factory()->enabled()->forDesk($this->desk)->create(['name' => 'Triage', 'description' => 'Sorts', 'position' => 4]);
    Automation::factory()->triggeredBy('ticket.updated', 'add_note', ['body' => 'x'])->create(['position' => 9]);

    $this->actingAs($this->lead)->post(route('automations.duplicate', $source))->assertRedirect();

    $copy = Automation::query()->where('name', 'Triage (copy)')->sole();
    expect($copy->enabled)->toBeFalse()
        ->and($copy->position)->toBe(5)
        ->and($copy->desk_id)->toBe($this->desk->id)
        ->and($copy->description)->toBe('Sorts')
        ->and($copy->trigger_type)->toBe('ticket.created')
        ->and($copy->definition->toArray())->toBe($source->definition->toArray());
});

test('a duplicate over the API returns the copy', function () {
    $source = Automation::factory()->enabled()->create(['name' => 'Triage']);
    actAs($this->admin, $this->workspace);

    $this->postJson("/api/v1/automations/{$source->id}/duplicate")->assertCreated()
        ->assertJsonPath('name', 'Triage (copy)')->assertJsonPath('enabled', false)->assertJsonPath('position', 2);
});

test('a lead can not duplicate a workspace-wide automation', function () {
    $source = Automation::factory()->create();

    $this->actingAs($this->lead)->post(route('automations.duplicate', $source))->assertForbidden();
    expect(Automation::query()->count())->toBe(1);
});

test('an automation is deleted with its runs', function () {
    $automation = Automation::factory()->create();
    runFor($automation, AutomationRunStatus::COMPLETED);

    $this->actingAs($this->admin)->delete(route('automations.destroy', $automation))->assertRedirect();

    expect(Automation::query()->count())->toBe(0)->and(AutomationRun::query()->count())->toBe(0);
});

test('an agent can not toggle, reorder, duplicate or delete', function () {
    $automation = Automation::factory()->forDesk($this->desk)->create();

    $this->actingAs($this->agent);
    $this->put(route('automations.enabled.update', $automation), ['enabled' => true])->assertForbidden();
    $this->post(route('automations.reorder'), ['ids' => [$automation->id]])->assertForbidden();
    $this->post(route('automations.duplicate', $automation))->assertForbidden();
    $this->delete(route('automations.destroy', $automation))->assertForbidden();
});

test('the API list carries each automation\'s last run without its log', function () {
    $automation = Automation::factory()->create();
    $run = runFor($automation, AutomationRunStatus::COMPLETED);
    actAs($this->admin, $this->workspace);

    $this->getJson('/api/v1/automations')->assertOk()
        ->assertJsonPath('0.last_run.id', $run->id)
        ->assertJsonPath('0.runs_last24h', 1)
        ->assertJsonMissingPath('0.last_run.log');
});
