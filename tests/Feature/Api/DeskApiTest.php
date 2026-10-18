<?php

use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
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

    $this->acmeDesk = $this->acme->execute(fn () => Desk::sole());
    $this->otherDesk = $this->other->execute(fn () => Desk::sole());
});

test('admins list every desk and agents only their own', function () {
    $this->acme->execute(fn () => Desk::factory()->create(['name' => 'Zeta']));

    actAs($this->admin, $this->acme);
    $this->getJson('http://acme.harbor.test/api/v1/desks')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $this->acmeDesk->id);

    actAs($this->agent, $this->acme);
    $this->getJson('http://acme.harbor.test/api/v1/desks')->assertOk()->assertJsonCount(0, 'data');
});

test('another workspace\'s desks are invisible', function () {
    actAs($this->admin, $this->acme);

    $this->getJson('http://acme.harbor.test/api/v1/desks')
        ->assertJsonMissing(['id' => $this->otherDesk->id]);

    $this->getJson("http://acme.harbor.test/api/v1/desks/{$this->otherDesk->id}")->assertNotFound();
    $this->patchJson("http://acme.harbor.test/api/v1/desks/{$this->otherDesk->id}", ['name' => 'Hijacked'])->assertNotFound();
    $this->putJson("http://acme.harbor.test/api/v1/desks/{$this->otherDesk->id}/members/{$this->agent->id}", ['role' => 'agent'])->assertNotFound();

    expect($this->otherDesk->fresh()->name)->not->toBe('Hijacked');
});

test('admins create desks and agents cannot', function () {
    actAs($this->admin, $this->acme);
    $this->postJson('http://acme.harbor.test/api/v1/desks', ['name' => 'North High'])
        ->assertCreated()
        ->assertJsonPath('slug', 'north-high');

    $this->postJson('http://acme.harbor.test/api/v1/desks', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    actAs($this->agent, $this->acme);
    $this->postJson('http://acme.harbor.test/api/v1/desks', ['name' => 'South High'])->assertForbidden();
});

test('leads update their desk and agents cannot', function () {
    $this->acmeDesk->users()->attach($this->agent, ['role' => DeskRole::AGENT, 'workspace_id' => $this->acme->id]);

    actAs($this->agent, $this->acme);
    $this->getJson("http://acme.harbor.test/api/v1/desks/{$this->acmeDesk->id}")->assertOk();
    $this->patchJson("http://acme.harbor.test/api/v1/desks/{$this->acmeDesk->id}", ['name' => 'Renamed'])->assertForbidden();

    $this->acmeDesk->users()->updateExistingPivot($this->agent->id, ['role' => DeskRole::LEAD]);

    $this->patchJson("http://acme.harbor.test/api/v1/desks/{$this->acmeDesk->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('name', 'Renamed');
});

test('desks are archived and restored, but never the last one', function () {
    actAs($this->admin, $this->acme);

    $this->deleteJson("http://acme.harbor.test/api/v1/desks/{$this->acmeDesk->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('desk');

    $desk = $this->acme->execute(fn () => Desk::factory()->create());

    $this->deleteJson("http://acme.harbor.test/api/v1/desks/{$desk->id}")->assertNoContent();
    $this->getJson("http://acme.harbor.test/api/v1/desks/{$desk->id}")->assertNotFound();

    $this->postJson("http://acme.harbor.test/api/v1/desks/{$desk->id}/restore")
        ->assertOk()
        ->assertJsonPath('archived_at', null);
});

test('members are added, listed with their role and removed', function () {
    actAs($this->admin, $this->acme);
    $membersUrl = "http://acme.harbor.test/api/v1/desks/{$this->acmeDesk->id}/members";

    $this->putJson("{$membersUrl}/{$this->agent->id}", ['role' => 'lead'])->assertNoContent();

    $this->getJson($membersUrl)
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->agent->id)
        ->assertJsonPath('data.0.role', 'lead');

    $this->putJson("{$membersUrl}/{$this->agent->id}", ['role' => 'boss'])->assertJsonValidationErrors('role');

    $stranger = User::factory()->create();
    $this->putJson("{$membersUrl}/{$stranger->id}", ['role' => 'agent'])->assertJsonValidationErrors('user');

    $this->deleteJson("{$membersUrl}/{$this->agent->id}")->assertNoContent();
    $this->getJson($membersUrl)->assertJsonCount(0, 'data');
});
