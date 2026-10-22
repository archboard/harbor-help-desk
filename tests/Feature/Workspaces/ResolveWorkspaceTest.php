<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->user = User::factory()->create();
    $this->acme->users()->attach($this->user, ['role' => WorkspaceRole::ADMIN]);
    $this->other->users()->attach($this->user, ['role' => WorkspaceRole::ADMIN]);
});

test('a request finds its workspace by host, not by whichever one is still current', function () {
    $this->other->makeCurrent();
    actAs($this->user, $this->acme);

    $this->getJson('http://acme.harbor.test/api/v1/workspace')->assertOk()->assertJsonPath('id', $this->acme->id);
    expect(Workspace::current()->id)->toBe($this->acme->id);
});

test('an unknown host is not served by a leftover current workspace', function () {
    $this->acme->makeCurrent();
    actAs($this->user, $this->acme);

    $this->getJson('http://nobody.harbor.test/api/v1/workspace')->assertNotFound();
    $this->getJson('http://harbor.test/api/v1/workspace')->assertNotFound();
});
