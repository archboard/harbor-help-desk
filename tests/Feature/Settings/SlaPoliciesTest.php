<?php

use App\Enums\WorkspaceRole;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ['workspace' => $this->workspace, 'agent' => $this->agent] = acmeWithAgent();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->payload = [
        'name' => 'Standard',
        'warning_percent' => 80,
        'targets' => ['urgent' => ['first_response' => 60, 'resolution' => 495]],
    ];
});

test('members see the policies but only admins can manage them', function () {
    $policy = SlaPolicy::factory()->create();

    $this->actingAs($this->agent)->get(route('sla-policies.index'))->assertInertia(fn (Assert $page) => $page
        ->component('settings/SlaPolicies')
        ->has('policies', 1)
        ->where('policies.0.id', $policy->id)
        ->where('priorities.urgent', 'Urgent')
        ->where('metrics.first_response', 'First response')
        ->where('canManage', false));

    $this->post(route('sla-policies.store'), $this->payload)->assertForbidden();
    $this->patch(route('sla-policies.update', $policy), ['name' => 'X'])->assertForbidden();
    $this->delete(route('sla-policies.destroy', $policy))->assertForbidden();

    $this->actingAs($this->admin)->get(route('sla-policies.index'))->assertInertia(fn (Assert $page) => $page->where('canManage', true));
});

test('admins create, update and delete policies', function () {
    $this->actingAs($this->admin);

    $this->post(route('sla-policies.store'), $this->payload)->assertRedirect()->assertSessionHasNoErrors();
    $policy = SlaPolicy::sole();
    expect($policy->targets['urgent'])->toEqual(['first_response' => 60, 'resolution' => 495])
        ->and($policy->warning_percent)->toBe(80);

    $this->patch(route('sla-policies.update', $policy), ['name' => 'Renamed'])->assertSessionHasNoErrors();
    expect($policy->fresh()->name)->toBe('Renamed');

    $this->post(route('sla-policies.store'), [...$this->payload, 'targets' => ['urgent' => ['first_response' => 0]]])
        ->assertSessionHasErrors('targets.urgent.first_response');

    $this->delete(route('sla-policies.destroy', $policy))->assertRedirect();
    expect(SlaPolicy::count())->toBe(0);
});

test('another workspace\'s policies are invisible and untouchable', function () {
    Workspace::factory()->create(['subdomain' => 'other'])->makeCurrent();
    $theirs = SlaPolicy::factory()->create();
    $this->workspace->makeCurrent();

    $this->actingAs($this->admin)->get(route('sla-policies.index'))->assertInertia(fn (Assert $page) => $page->has('policies', 0));
    $this->patch(route('sla-policies.update', $theirs), ['name' => 'X'])->assertNotFound();
    $this->delete(route('sla-policies.destroy', $theirs))->assertNotFound();
    expect($theirs->fresh()->name)->not->toBe('X');
});
