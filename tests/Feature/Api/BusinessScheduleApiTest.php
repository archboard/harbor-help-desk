<?php

use App\Enums\WorkspaceRole;
use App\Models\BusinessSchedule;
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

    $this->url = 'http://acme.harbor.test/api/v1/business-schedules';
    $this->payload = [
        'name' => 'Support hours',
        'timezone' => 'America/Chicago',
        'weekly_hours' => ['mon' => [['09:00', '12:00'], ['13:00', '24:00']]],
        'holidays' => ['2026-12-25'],
    ];
});

test('admins create schedules and every member can read them', function () {
    actAs($this->admin, $this->acme);

    $response = $this->postJson($this->url, $this->payload)->assertCreated();
    $response->assertJsonPath('weekly_hours.mon', [['09:00', '12:00'], ['13:00', '24:00']])
        ->assertJsonPath('weekly_hours.tue', [])
        ->assertJsonPath('holidays', ['2026-12-25'])
        ->assertJsonPath('timezone', 'America/Chicago');

    actAs($this->agent, $this->acme);
    $this->getJson($this->url)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $response->json('id'));
    $this->getJson("{$this->url}/{$response->json('id')}")->assertOk()->assertJsonPath('name', 'Support hours');
});

test('agents cannot manage schedules', function () {
    $schedule = $this->acme->execute(fn () => BusinessSchedule::factory()->create());
    actAs($this->agent, $this->acme);

    $this->postJson($this->url, $this->payload)->assertForbidden();
    $this->patchJson("{$this->url}/{$schedule->id}", ['name' => 'X'])->assertForbidden();
    $this->deleteJson("{$this->url}/{$schedule->id}")->assertForbidden();
});

test('hours are validated and every error is reported at once', function (array $overrides, array $errors) {
    actAs($this->admin, $this->acme);

    $this->postJson($this->url, [...$this->payload, ...$overrides])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'bad timezone' => [['timezone' => 'Mars/Base'], ['timezone']],
    'unknown day' => [['weekly_hours' => ['xyz' => [['09:00', '17:00']]]], ['weekly_hours']],
    'bad time format' => [['weekly_hours' => ['mon' => [['9am', '17:00']]]], ['weekly_hours.mon.0.0']],
    'start not before end' => [['weekly_hours' => ['mon' => [['09:00', '17:00'], ['18:00', '18:00']]]], ['weekly_hours.mon.1']],
    'overlap' => [['weekly_hours' => ['mon' => [['09:00', '12:00'], ['11:00', '17:00']]]], ['weekly_hours.mon']],
    'no intervals' => [['weekly_hours' => ['mon' => [], 'tue' => []]], ['weekly_hours']],
    'bad holiday' => [['holidays' => ['12/25/2026']], ['holidays.0']],
    'several at once' => [['name' => '', 'timezone' => 'nope', 'weekly_hours' => ['mon' => [['17:00', '09:00']]]], ['name', 'timezone', 'weekly_hours.mon.0']],
]);

test('admins update and delete schedules', function () {
    $schedule = $this->acme->execute(fn () => BusinessSchedule::factory()->create());
    actAs($this->admin, $this->acme);

    $this->patchJson("{$this->url}/{$schedule->id}", ['name' => 'Renamed', 'holidays' => ['2026-01-01']])
        ->assertOk()
        ->assertJsonPath('name', 'Renamed')
        ->assertJsonPath('holidays', ['2026-01-01'])
        ->assertJsonPath('weekly_hours.mon', [['09:00', '17:00']]);
    $this->patchJson("{$this->url}/{$schedule->id}", ['weekly_hours' => ['mon' => []]])->assertJsonValidationErrors('weekly_hours');
    $this->patchJson("{$this->url}/{$schedule->id}", ['weekly_hours' => []])->assertJsonValidationErrors('weekly_hours');
    $this->deleteJson("{$this->url}/{$schedule->id}")->assertNoContent();

    expect(BusinessSchedule::withoutGlobalScopes()->count())->toBe(0);
});

test('another workspace\'s schedules are invisible and untouchable', function () {
    $theirs = $this->other->execute(fn () => BusinessSchedule::factory()->create(['name' => 'Theirs']));
    actAs($this->admin, $this->acme);

    $this->getJson($this->url)->assertOk()->assertJsonCount(0);
    $this->getJson("{$this->url}/{$theirs->id}")->assertNotFound();
    $this->patchJson("{$this->url}/{$theirs->id}", ['name' => 'Mine'])->assertNotFound();
    $this->deleteJson("{$this->url}/{$theirs->id}")->assertNotFound();

    expect($theirs->fresh()->name)->toBe('Theirs');
});
