<?php

use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->desk = Desk::sole();

    foreach (['owner' => WorkspaceRole::OWNER, 'admin' => WorkspaceRole::ADMIN, 'agent' => WorkspaceRole::AGENT] as $name => $role) {
        $this->{$name} = User::factory()->create();
        $this->workspace->users()->attach($this->{$name}, ['role' => $role]);
    }
});

test('the members page lists members, invitations and the roles the actor can grant', function () {
    $this->desk->users()->attach($this->agent, ['role' => 'agent', 'workspace_id' => $this->workspace->id]);
    $invitation = WorkspaceInvitation::factory()->create(['desk_id' => $this->desk->id, 'invited_by_id' => $this->admin->id]);

    $this->actingAs($this->owner)
        ->get(route('workspace.members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/Members')
            ->has('members', 3)
            ->where('members', fn ($members) => collect($members)->firstWhere('id', $this->agent->id)['desk_count'] === 1
                && collect($members)->firstWhere('id', $this->admin->id)['role'] === 'admin')
            ->where('invitations.0.id', $invitation->id)
            ->where('invitations.0.desk.id', $this->desk->id)
            ->where('invitations.0.invited_by.id', $this->admin->id)
            ->has('invitations.0.expires_at')
            ->where('roles', ['owner' => 'Owner', 'admin' => 'Admin', 'agent' => 'Agent'])
            ->where('inviteRoles', ['admin' => 'Admin', 'agent' => 'Agent'])
            ->where('roleLabels', ['owner' => 'Owner', 'admin' => 'Admin', 'agent' => 'Agent']));

    $this->actingAs($this->admin)
        ->get(route('workspace.members.index'))
        ->assertInertia(fn (Assert $page) => $page->where('roles', ['admin' => 'Admin', 'agent' => 'Agent']));
});

test('agents cannot open the members page or change anything', function () {
    $invitation = WorkspaceInvitation::factory()->create();

    $this->actingAs($this->agent);

    $this->get(route('workspace.members.index'))->assertForbidden();
    $this->post(route('workspace.invitations.store'), ['email' => 'new@example.com', 'role' => 'agent'])->assertForbidden();
    $this->delete(route('workspace.invitations.destroy', $invitation))->assertForbidden();
    $this->post(route('workspace.invitations.resend', $invitation))->assertForbidden();
    $this->put(route('workspace.members.update', $this->admin), ['role' => 'agent'])->assertForbidden();
    $this->delete(route('workspace.members.destroy', $this->admin))->assertForbidden();

    expect($this->admin->workspaceRole($this->workspace))->toBe(WorkspaceRole::ADMIN)
        ->and(WorkspaceInvitation::count())->toBe(1);
});

test('an admin invites, resends and revokes', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('workspace.invitations.store'), ['email' => 'new@example.com', 'role' => 'agent', 'desk_id' => $this->desk->id])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Invitation sent.');

    $invitation = WorkspaceInvitation::sole();
    Notification::assertSentOnDemandTimes(WorkspaceInvitationNotification::class, 1);

    $this->post(route('workspace.invitations.resend', $invitation))->assertRedirect();
    Notification::assertSentOnDemandTimes(WorkspaceInvitationNotification::class, 2);

    $this->delete(route('workspace.invitations.destroy', $invitation))->assertRedirect();
    expect(WorkspaceInvitation::count())->toBe(0);
});

test('resending is throttled', function () {
    Notification::fake();
    $invitation = WorkspaceInvitation::factory()->create();

    $this->actingAs($this->admin);

    foreach (range(1, 6) as $ignored) {
        $this->post(route('workspace.invitations.resend', $invitation))->assertRedirect();
    }

    $this->post(route('workspace.invitations.resend', $invitation))->assertStatus(429);
});

test('an admin changes a role and removes a member', function () {
    $this->actingAs($this->admin)
        ->put(route('workspace.members.update', $this->agent), ['role' => 'admin'])
        ->assertRedirect();

    expect($this->agent->workspaceRole($this->workspace))->toBe(WorkspaceRole::ADMIN);

    $this->delete(route('workspace.members.destroy', $this->agent))->assertRedirect();

    expect($this->workspace->users()->whereKey($this->agent->id)->exists())->toBeFalse();
});

test('leaving the workspace signs you out', function () {
    $this->actingAs($this->agent)
        ->delete(route('workspace.members.destroy', $this->agent))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect($this->workspace->users()->whereKey($this->agent->id)->exists())->toBeFalse();
});

test('another workspace\'s admin sees and changes none of this workspace\'s members', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    URL::forceRootUrl(rtrim($this->workspace->url(), '/'));

    $invitation = WorkspaceInvitation::factory()->create();

    $other = Workspace::factory()->create();
    $outsider = User::factory()->create();
    $other->users()->attach($outsider, ['role' => WorkspaceRole::ADMIN]);
    $other->makeCurrent();
    URL::forceRootUrl(rtrim($other->url(), '/'));

    $this->actingAs($outsider)
        ->get(route('workspace.members.index'))
        ->assertInertia(fn (Assert $page) => $page->has('members', 1)->where('members.0.id', $outsider->id)->has('invitations', 0));

    $this->delete(route('workspace.invitations.destroy', $invitation->id))->assertNotFound();
    $this->post(route('workspace.invitations.resend', $invitation->id))->assertNotFound();
    $this->put(route('workspace.members.update', $this->agent), ['role' => 'admin'])->assertNotFound();
    $this->delete(route('workspace.members.destroy', $this->agent))->assertNotFound();

    expect($this->agent->workspaceRole($this->workspace))->toBe(WorkspaceRole::AGENT)
        ->and(WorkspaceInvitation::withoutGlobalScopes()->count())->toBe(1);
});
