<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme', 'name' => 'Acme']);
    $this->other = Workspace::factory()->create(['subdomain' => 'other', 'name' => 'Other']);

    $this->admin = User::factory()->create();
    $this->acme->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->agent = User::factory()->create();
    $this->acme->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
});

test('members read the workspace and admins update it', function () {
    actAs($this->agent, $this->acme);
    $this->getJson('http://acme.harbor.test/api/v1/workspace')
        ->assertOk()
        ->assertExactJson(['id' => $this->acme->id, 'name' => 'Acme', 'subdomain' => 'acme', 'is_admin' => false, 'away_at' => null]);

    actAs($this->admin, $this->acme);
    $this->patchJson('http://acme.harbor.test/api/v1/workspace', ['name' => 'Acme Support'])
        ->assertOk()
        ->assertExactJson(['id' => $this->acme->id, 'name' => 'Acme Support', 'subdomain' => 'acme', 'is_admin' => true, 'away_at' => null, 'settings' => ['mail_notification_mailbox_id' => null, 'mail_new_tickets_per_sender_per_hour' => 20, 'attachments_max_size_kb' => 25600, 'attachments_blocked_extensions' => ['bat', 'cmd', 'com', 'cpl', 'dll', 'exe', 'hta', 'jar', 'js', 'jse', 'lnk', 'msi', 'msp', 'ps1', 'scr', 'sh', 'vbe', 'vbs', 'wsf']]]);
});

test('agents cannot update the workspace', function () {
    actAs($this->agent, $this->acme);

    $this->patchJson('http://acme.harbor.test/api/v1/workspace', ['name' => 'Renamed'])->assertForbidden();

    expect($this->acme->fresh()->name)->toBe('Acme');
});

test('a token scoped to one workspace cannot read or update another', function () {
    $this->other->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    actAs($this->admin, $this->acme);

    $this->getJson('http://other.harbor.test/api/v1/workspace')->assertForbidden();
    $this->patchJson('http://other.harbor.test/api/v1/workspace', ['name' => 'Hijacked'])->assertForbidden();

    expect($this->other->fresh()->name)->toBe('Other');
});
