<?php

use App\Enums\WorkspaceRole;
use App\Models\BusinessSchedule;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ['workspace' => $this->workspace, 'agent' => $this->agent] = acmeWithAgent();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->payload = [
        'name' => 'Support hours',
        'timezone' => 'America/Chicago',
        'weekly_hours' => ['mon' => [['09:00', '12:00'], ['13:00', '24:00']]],
        'holidays' => ['2026-12-25'],
    ];
});

test('members see the schedules but only admins can manage them', function () {
    $schedule = BusinessSchedule::factory()->create();

    $this->actingAs($this->agent)->get(route('business-hours.index'))->assertInertia(fn (Assert $page) => $page
        ->component('settings/BusinessHours')
        ->has('schedules', 1)
        ->where('schedules.0.id', $schedule->id)
        ->where('canManage', false));

    $this->post(route('business-hours.store'), $this->payload)->assertForbidden();
    $this->patch(route('business-hours.update', $schedule), ['name' => 'X'])->assertForbidden();
    $this->delete(route('business-hours.destroy', $schedule))->assertForbidden();

    $this->actingAs($this->admin)->get(route('business-hours.index'))->assertInertia(fn (Assert $page) => $page->where('canManage', true));
});

test('admins create, update and delete schedules', function () {
    $this->actingAs($this->admin);

    $this->post(route('business-hours.store'), $this->payload)->assertRedirect()->assertSessionHasNoErrors();
    $schedule = BusinessSchedule::sole();
    expect($schedule->weekly_hours['mon'])->toBe([['09:00', '12:00'], ['13:00', '24:00']]);

    $this->patch(route('business-hours.update', $schedule), ['name' => 'Renamed'])->assertSessionHasNoErrors();
    expect($schedule->fresh()->name)->toBe('Renamed');

    $this->post(route('business-hours.store'), [...$this->payload, 'weekly_hours' => ['mon' => [['09:00', '12:00'], ['11:00', '13:00']]]])
        ->assertSessionHasErrors('weekly_hours.mon');

    $this->delete(route('business-hours.destroy', $schedule))->assertRedirect();
    expect(BusinessSchedule::count())->toBe(0);
});

test('another workspace\'s schedules are invisible and untouchable', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other'])->makeCurrent();
    $theirs = BusinessSchedule::factory()->create();
    $this->workspace->makeCurrent();

    $this->actingAs($this->admin)->get(route('business-hours.index'))->assertInertia(fn (Assert $page) => $page->has('schedules', 0));
    $this->patch(route('business-hours.update', $theirs), ['name' => 'X'])->assertNotFound();
    $this->delete(route('business-hours.destroy', $theirs))->assertNotFound();
    expect($theirs->fresh()->name)->not->toBe('X');
});
