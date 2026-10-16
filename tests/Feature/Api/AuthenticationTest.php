<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Passport\Passport;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->user = User::factory()->create();
    $this->acme->users()->attach($this->user, ['role' => WorkspaceRole::Agent]);
});

test('rejects requests without a token', function () {
    $this->getJson('http://acme.harbor.test/api/v1/user')->assertUnauthorized();
});

test('returns the authenticated member', function () {
    Passport::actingAs($this->user, [$this->acme->tokenScope()]);

    $this->getJson('http://acme.harbor.test/api/v1/user')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
        ]]);
});

test('rejects a token without the workspace scope', function () {
    Passport::actingAs($this->user);

    $this->getJson('http://acme.harbor.test/api/v1/user')->assertForbidden();
});

test('rejects a user calling a workspace they do not belong to', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    Passport::actingAs($this->user, [$other->tokenScope()]);

    $this->getJson('http://other.harbor.test/api/v1/user')->assertForbidden();
});

test('rejects a token scoped to another workspace the user also belongs to', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $other->users()->attach($this->user, ['role' => WorkspaceRole::Agent]);
    Passport::actingAs($this->user, [$this->acme->tokenScope()]);

    $this->getJson('http://other.harbor.test/api/v1/user')->assertForbidden();
});

test('rejects a scoped token once the user leaves the workspace', function () {
    $this->acme->users()->detach($this->user);
    Passport::actingAs($this->user, [$this->acme->tokenScope()]);

    $this->getJson('http://acme.harbor.test/api/v1/user')->assertForbidden();
});

test('returns 404 for an unknown subdomain', function () {
    Passport::actingAs($this->user);

    $this->getJson('http://nope.harbor.test/api/v1/user')->assertNotFound();
});

test('self hosted resolves the single workspace on any host', function () {
    config(['harbor.multitenant' => false]);
    Passport::actingAs($this->user, [$this->acme->tokenScope()]);

    $this->getJson('http://help.example.com/api/v1/user')->assertOk();
});
