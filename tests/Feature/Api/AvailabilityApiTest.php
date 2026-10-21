<?php

use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);

    $this->agent = User::factory()->create();
    $this->acme->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
    $this->other->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
});

test('an agent toggles away in the workspace they call', function () {
    actAs($this->agent, $this->acme);

    $this->putJson('http://acme.harbor.test/api/v1/availability', ['away' => true])->assertNoContent();
    expect($this->agent->membership($this->acme)->away_at)->not->toBeNull();

    $this->putJson('http://acme.harbor.test/api/v1/availability', ['away' => false])->assertNoContent();
    expect($this->agent->membership($this->acme)->away_at)->toBeNull();
});

test('going away in workspace A leaves workspace B untouched', function () {
    actAs($this->agent, $this->acme);

    $this->putJson('http://acme.harbor.test/api/v1/availability', ['away' => true])->assertNoContent();

    expect($this->agent->membership($this->other)->away_at)->toBeNull();
});

test('away must be a boolean', function () {
    actAs($this->agent, $this->acme);

    $this->putJson('http://acme.harbor.test/api/v1/availability', ['away' => 'maybe'])->assertJsonValidationErrors('away');
});

test('the rotation flag follows the desk member endpoint and stays in its workspace', function () {
    $admin = User::factory()->create();
    $this->acme->users()->attach($admin, ['role' => WorkspaceRole::ADMIN]);
    $acmeDesk = $this->acme->execute(fn () => Desk::sole());
    $otherDesk = $this->other->execute(fn () => Desk::sole());

    actAs($admin, $this->acme);
    $this->putJson("http://acme.harbor.test/api/v1/desks/{$acmeDesk->id}/members/{$this->agent->id}", ['role' => 'agent', 'in_rotation' => false])->assertNoContent();
    $this->getJson("http://acme.harbor.test/api/v1/desks/{$acmeDesk->id}/members")->assertJsonPath('data.0.in_rotation', false);

    $this->putJson("http://acme.harbor.test/api/v1/desks/{$otherDesk->id}/members/{$this->agent->id}", ['role' => 'agent', 'in_rotation' => false])->assertNotFound();
});
