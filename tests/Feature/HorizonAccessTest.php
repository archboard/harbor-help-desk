<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();

    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
});

test('self-hosted, workspace admins open Horizon and agents are denied', function () {
    config(['harbor.multitenant' => false]);

    $agent = User::factory()->create();
    $this->workspace->users()->attach($agent, ['role' => WorkspaceRole::AGENT]);

    $this->actingAs($this->admin)->get('/horizon')->assertOk();
    $this->actingAs($agent)->get('/horizon')->assertForbidden();
});

test('multitenant, only verified allowlisted operators open Horizon', function () {
    config(['harbor.multitenant' => true, 'harbor.horizon_emails' => ['ops@example.com', 'new@example.com']]);

    $operator = User::factory()->create(['email' => 'Ops@Example.com']);
    $unverified = User::factory()->unverified()->create(['email' => 'new@example.com']);

    $this->actingAs($this->admin)->get('/horizon')->assertForbidden();
    $this->actingAs($unverified)->get('/horizon')->assertForbidden();
    $this->actingAs($operator)->get('/horizon')->assertOk();
});
