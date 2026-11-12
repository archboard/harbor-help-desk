<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);

    $this->admin = User::factory()->create();
    $this->acme->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->agent = User::factory()->create();
    $this->acme->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);

    $this->base = 'http://acme.harbor.test/api/v1';
});

function acmeInvitation(array $attributes = []): WorkspaceInvitation
{
    return test()->acme->execute(fn () => WorkspaceInvitation::factory()->create($attributes));
}

test('admins list members and invitations', function () {
    $invitation = acmeInvitation(['invited_by_id' => $this->admin->id]);
    actAs($this->admin, $this->acme);

    $this->getJson("{$this->base}/members")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.desk_count', 0)
        ->assertJsonPath('data.*.role', fn (array $roles) => collect($roles)->sort()->values()->all() === ['admin', 'agent'])
        ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'desk_count', 'joined_at']], 'meta']);

    $this->getJson("{$this->base}/invitations")
        ->assertOk()
        ->assertJsonPath('data.0.id', $invitation->id)
        ->assertJsonPath('data.0.role', 'agent')
        ->assertJsonPath('data.0.invited_by.id', $this->admin->id)
        ->assertJsonPath('data.0.desk', null)
        ->assertJsonStructure(['data' => [['expires_at', 'created_at']], 'meta']);
});

test('an admin invites, and a duplicate is rejected', function () {
    Notification::fake();
    actAs($this->admin, $this->acme);

    $this->postJson("{$this->base}/invitations", ['email' => 'new@example.com', 'role' => 'agent'])
        ->assertCreated()
        ->assertJsonPath('email', 'new@example.com')
        ->assertJsonPath('role', 'agent');
    Notification::assertSentOnDemandTimes(WorkspaceInvitationNotification::class, 1);

    $this->postJson("{$this->base}/invitations", ['email' => 'NEW@example.com', 'role' => 'agent'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('an admin resends and revokes an invitation', function () {
    Notification::fake();
    $invitation = acmeInvitation();
    actAs($this->admin, $this->acme);

    $this->postJson("{$this->base}/invitations/{$invitation->id}/resend")
        ->assertCreated()
        ->assertJsonPath('id', $invitation->id);
    Notification::assertSentOnDemandTimes(WorkspaceInvitationNotification::class, 1);

    $this->deleteJson("{$this->base}/invitations/{$invitation->id}")->assertNoContent();
    expect(WorkspaceInvitation::withoutGlobalScopes()->count())->toBe(0);
});

test('an admin changes a role and removes a member', function () {
    actAs($this->admin, $this->acme);

    $this->putJson("{$this->base}/members/{$this->agent->id}", ['role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('id', $this->agent->id)
        ->assertJsonPath('role', 'admin');

    $this->deleteJson("{$this->base}/members/{$this->agent->id}")->assertNoContent();
    expect($this->acme->users()->whereKey($this->agent->id)->exists())->toBeFalse();
});

test('agents are forbidden everywhere', function () {
    $invitation = acmeInvitation();
    actAs($this->agent, $this->acme);

    $this->getJson("{$this->base}/members")->assertForbidden();
    $this->getJson("{$this->base}/invitations")->assertForbidden();
    $this->postJson("{$this->base}/invitations", ['email' => 'new@example.com', 'role' => 'agent'])->assertForbidden();
    $this->deleteJson("{$this->base}/invitations/{$invitation->id}")->assertForbidden();
    $this->postJson("{$this->base}/invitations/{$invitation->id}/resend")->assertForbidden();
    $this->putJson("{$this->base}/members/{$this->admin->id}", ['role' => 'agent'])->assertForbidden();
    $this->deleteJson("{$this->base}/members/{$this->admin->id}")->assertForbidden();

    expect($this->admin->workspaceRole($this->acme))->toBe(WorkspaceRole::ADMIN);
});

test('a token scoped to another workspace cannot reach these endpoints', function () {
    $outsider = User::factory()->create();
    $this->other->users()->attach($outsider, ['role' => WorkspaceRole::ADMIN]);
    Passport::actingAs($outsider, [$this->other->tokenScope()]);

    $this->getJson("{$this->base}/members")->assertForbidden();
    $this->getJson("{$this->base}/invitations")->assertForbidden();
});

test('another workspace admin cannot touch this workspace invitations or members', function () {
    $invitation = acmeInvitation();
    $outsider = User::factory()->create();
    $this->other->users()->attach($outsider, ['role' => WorkspaceRole::ADMIN]);
    actAs($outsider, $this->other);
    $otherBase = 'http://other.harbor.test/api/v1';

    $this->deleteJson("{$otherBase}/invitations/{$invitation->id}")->assertNotFound();
    $this->postJson("{$otherBase}/invitations/{$invitation->id}/resend")->assertNotFound();
    $this->putJson("{$otherBase}/members/{$this->agent->id}", ['role' => 'admin'])->assertNotFound();
    $this->deleteJson("{$otherBase}/members/{$this->agent->id}")->assertNotFound();
    $this->getJson("{$otherBase}/members")->assertJsonCount(1, 'data');

    expect($this->agent->workspaceRole($this->acme))->toBe(WorkspaceRole::AGENT)
        ->and(WorkspaceInvitation::withoutGlobalScopes()->count())->toBe(1);
});
