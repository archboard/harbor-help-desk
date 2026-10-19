<?php

use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->user = User::factory()->create();
});

test('resolves the workspace from the subdomain', function () {
    Workspace::factory()->create(['subdomain' => 'other']);
    $acme = Workspace::factory()->create(['subdomain' => 'acme']);

    $this->actingAs($this->user)
        ->get('http://acme.harbor.test/dashboard')
        ->assertRedirect('/tickets');

    expect($acme->isCurrent())->toBeTrue();
});

test('returns 404 for an unknown subdomain', function () {
    Workspace::factory()->create(['subdomain' => 'acme']);

    $this->actingAs($this->user)
        ->get('http://nope.harbor.test/dashboard')
        ->assertNotFound();
});

test('returns 404 for the root domain or a nested subdomain', function () {
    Workspace::factory()->create(['subdomain' => 'acme']);

    $this->actingAs($this->user)->get('http://harbor.test/dashboard')->assertNotFound();
    $this->actingAs($this->user)->get('http://x.acme.harbor.test/dashboard')->assertNotFound();
    $this->actingAs($this->user)->get('http://acme.evil.test/dashboard')->assertNotFound();
});

test('self hosted falls back to the single workspace on any host', function () {
    config(['harbor.multitenant' => false]);
    $workspace = Workspace::factory()->create(['subdomain' => 'acme']);

    $this->actingAs($this->user)
        ->get('http://help.example.com/dashboard')
        ->assertRedirect('/tickets');

    expect($workspace->isCurrent())->toBeTrue();
});

test('rejects a session from another workspace with 401', function () {
    $acme = Workspace::factory()->create(['subdomain' => 'acme']);
    Workspace::factory()->create(['subdomain' => 'other']);

    $this->actingAs($this->user)
        ->withSession(['ensure_valid_tenant_session_tenant_id' => $acme->id])
        ->get('http://other.harbor.test/dashboard')
        ->assertUnauthorized();
});
