<?php

use App\Enums\SlaMetric;
use App\Enums\TicketPriority;
use App\Enums\WorkspaceRole;
use App\Models\BusinessSchedule;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);

    $this->admin = User::factory()->create();
    $this->acme->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->agent = User::factory()->create();
    $this->acme->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);

    $this->url = 'http://acme.harbor.test/api/v1/sla-policies';
    $this->payload = [
        'name' => 'Standard',
        'targets' => ['urgent' => ['first_response' => 60, 'resolution' => 480], 'low' => ['resolution' => 4320]],
    ];
});

test('admins create policies and every member can read them', function () {
    $schedule = $this->acme->execute(fn () => BusinessSchedule::factory()->create());
    actAs($this->admin, $this->acme);

    $response = $this->postJson($this->url, [...$this->payload, 'business_schedule_id' => $schedule->id, 'warning_percent' => 80])->assertCreated();
    $response->assertJsonPath('targets.urgent.first_response', 60)
        ->assertJsonPath('targets.low.resolution', 4320)
        ->assertJsonPath('warning_percent', 80)
        ->assertJsonPath('business_schedule_id', $schedule->id);

    actAs($this->agent, $this->acme);
    $this->getJson($this->url)->assertOk()->assertJsonCount(1)->assertJsonPath('0.business_schedule.id', $schedule->id);
    $this->getJson("{$this->url}/{$response->json('id')}")->assertOk()->assertJsonPath('name', 'Standard');
});

test('a policy defaults to 24/7 and a 75 percent warning', function () {
    actAs($this->admin, $this->acme);

    $this->postJson($this->url, $this->payload)
        ->assertCreated()
        ->assertJsonPath('business_schedule_id', null)
        ->assertJsonPath('warning_percent', 75);
});

test('agents cannot manage policies', function () {
    $policy = $this->acme->execute(fn () => SlaPolicy::factory()->create());
    actAs($this->agent, $this->acme);

    $this->postJson($this->url, $this->payload)->assertForbidden();
    $this->patchJson("{$this->url}/{$policy->id}", ['name' => 'X'])->assertForbidden();
    $this->deleteJson("{$this->url}/{$policy->id}")->assertForbidden();
});

test('targets and settings are validated and every error is reported at once', function (array $overrides, array $errors) {
    actAs($this->admin, $this->acme);

    $this->postJson($this->url, [...$this->payload, ...$overrides])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'unknown priority' => [['targets' => ['critical' => ['resolution' => 60]]], ['targets']],
    'unknown metric' => [['targets' => ['urgent' => ['reply' => 60]]], ['targets.urgent']],
    'zero minutes' => [['targets' => ['urgent' => ['resolution' => 0]]], ['targets.urgent.resolution']],
    'fractional minutes' => [['targets' => ['urgent' => ['resolution' => 1.5]]], ['targets.urgent.resolution']],
    'warning too high' => [['warning_percent' => 100], ['warning_percent']],
    'warning too low' => [['warning_percent' => 0], ['warning_percent']],
    'unknown schedule' => [['business_schedule_id' => 'bsc_missing'], ['business_schedule_id']],
    'several at once' => [['name' => '', 'warning_percent' => 150, 'targets' => ['urgent' => ['resolution' => -5]]], ['name', 'warning_percent', 'targets.urgent.resolution']],
]);

test('targets are required on create but may be empty', function () {
    actAs($this->admin, $this->acme);

    $this->postJson($this->url, ['name' => 'Bare'])->assertJsonValidationErrors('targets');
    $this->postJson($this->url, ['name' => 'Bare', 'targets' => []])->assertCreated();
});

test('admins update and delete policies', function () {
    $schedule = $this->acme->execute(fn () => BusinessSchedule::factory()->create());
    $policy = $this->acme->execute(fn () => SlaPolicy::factory()->create(['business_schedule_id' => $schedule->id]));
    actAs($this->admin, $this->acme);

    $this->patchJson("{$this->url}/{$policy->id}", ['name' => 'Renamed', 'business_schedule_id' => null, 'warning_percent' => 50])
        ->assertOk()
        ->assertJsonPath('name', 'Renamed')
        ->assertJsonPath('business_schedule_id', null)
        ->assertJsonPath('warning_percent', 50)
        ->assertJsonPath('targets.urgent.first_response', 60);
    $this->patchJson("{$this->url}/{$policy->id}", ['targets' => ['normal' => ['resolution' => 10]]])
        ->assertOk()
        ->assertJsonPath('targets', ['normal' => ['resolution' => 10]]);
    $this->deleteJson("{$this->url}/{$policy->id}")->assertNoContent();

    expect(SlaPolicy::withoutGlobalScopes()->count())->toBe(0);
});

test('targetFor returns the minutes or null when untracked', function () {
    $policy = SlaPolicy::factory()->make(['targets' => ['urgent' => ['resolution' => 480]]]);

    expect($policy->targetFor(TicketPriority::URGENT, SlaMetric::RESOLUTION))->toBe(480)
        ->and($policy->targetFor(TicketPriority::URGENT, SlaMetric::FIRST_RESPONSE))->toBeNull()
        ->and($policy->targetFor(TicketPriority::LOW, SlaMetric::RESOLUTION))->toBeNull();
});

test('another workspace\'s policies and schedules are invisible and unusable', function () {
    $theirs = $this->other->execute(fn () => SlaPolicy::factory()->create(['name' => 'Theirs']));
    $theirSchedule = $this->other->execute(fn () => BusinessSchedule::factory()->create());
    $mine = $this->acme->execute(fn () => SlaPolicy::factory()->create());
    actAs($this->admin, $this->acme);

    $this->getJson($this->url)->assertOk()->assertJsonCount(1);
    $this->getJson("{$this->url}/{$theirs->id}")->assertNotFound();
    $this->patchJson("{$this->url}/{$theirs->id}", ['name' => 'Mine'])->assertNotFound();
    $this->deleteJson("{$this->url}/{$theirs->id}")->assertNotFound();
    $this->postJson($this->url, [...$this->payload, 'business_schedule_id' => $theirSchedule->id])->assertJsonValidationErrors('business_schedule_id');
    $this->patchJson("{$this->url}/{$mine->id}", ['business_schedule_id' => $theirSchedule->id])->assertJsonValidationErrors('business_schedule_id');

    expect($theirs->fresh()->name)->toBe('Theirs');
});
