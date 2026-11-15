<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

/**
 * @return array<string, string>
 */
function acceptInput(array $overrides = []): array
{
    return [
        'name' => 'Sam New',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        ...$overrides,
    ];
}

test('a guest with no account is asked to register', function () {
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com']);

    $this->get($invitation->acceptUrl())->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/AcceptInvitation')
        ->where('mode', 'register')
        ->where('invitation.email', 'sam@example.com')
        ->where('roleLabel', 'Agent')
        ->has('invitation.invited_by'));
});

test('a guest with an account is sent to sign in, and returns to the invitation', function () {
    User::factory()->create(['email' => 'Sam@Example.com']);
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com']);

    $this->get($invitation->acceptUrl())->assertInertia(fn (Assert $page) => $page->where('mode', 'login'));

    expect(session('url.intended'))->toBe($invitation->acceptUrl());
});

test('the invited user signed in can accept', function () {
    $user = User::factory()->create(['email' => 'sam@example.com']);
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com', 'role' => WorkspaceRole::ADMIN]);

    $this->actingAs($user)->get($invitation->acceptUrl())->assertInertia(fn (Assert $page) => $page->where('mode', 'accept'));

    $this->actingAs($user)->post($invitation->acceptUrl())->assertRedirect(route('tickets.index'));

    expect($user->workspaceRole($this->workspace))->toBe(WorkspaceRole::ADMIN)
        ->and(WorkspaceInvitation::count())->toBe(0);
});

test('accepting as an existing user keeps their password', function () {
    $user = User::factory()->create(['email' => 'sam@example.com', 'password' => 'old-password-123']);
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com']);

    $this->actingAs($user)->post($invitation->acceptUrl(), acceptInput())->assertRedirect(route('tickets.index'));

    expect(Hash::check('old-password-123', $user->fresh()->password))->toBeTrue();
});

test('someone signed in as another address sees a mismatch and cannot accept', function () {
    $other = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com']);

    $this->actingAs($other)->get($invitation->acceptUrl())->assertInertia(fn (Assert $page) => $page->where('mode', 'mismatch'));

    $this->actingAs($other)->post($invitation->acceptUrl())->assertSessionHasErrors('email');

    expect($other->workspaceRole($this->workspace))->toBeNull()
        ->and(WorkspaceInvitation::count())->toBe(1);
});

test('registering creates the account, signs it in and joins the workspace', function () {
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com']);

    $this->post($invitation->acceptUrl(), acceptInput())->assertRedirect(route('tickets.index'));

    $user = User::where('email', 'sam@example.com')->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->workspaceRole($this->workspace))->toBe(WorkspaceRole::AGENT)
        ->and(WorkspaceInvitation::count())->toBe(0);
});

test('registering needs a valid password', function () {
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com']);

    $this->post($invitation->acceptUrl(), acceptInput(['password_confirmation' => 'nope']))->assertSessionHasErrors('password');

    $this->assertGuest();
    expect(User::where('email', 'sam@example.com')->exists())->toBeFalse();
});

test('an expired link is forbidden', function () {
    $link = WorkspaceInvitation::factory()->create()->acceptUrl();
    $this->travel(8)->days();

    $this->get($link)->assertForbidden();
    $this->post($link, acceptInput())->assertForbidden();
});

test('a resent invitation gets a fresh link', function () {
    $invitation = WorkspaceInvitation::factory()->create();
    $this->travel(6)->days();
    $invitation->update(['created_at' => now()]);
    $this->travel(6)->days();

    $this->get($invitation->acceptUrl())->assertOk();
});

test('a tampered link is forbidden', function () {
    $invitation = WorkspaceInvitation::factory()->create();

    $this->get($invitation->acceptUrl().'&x=1')->assertForbidden();
    $this->post($invitation->acceptUrl().'&x=1', acceptInput())->assertForbidden();
    $this->get(route('invitations.show', $invitation))->assertForbidden();
});

test('a revoked invitation is gone', function () {
    $invitation = WorkspaceInvitation::factory()->create();
    $link = $invitation->acceptUrl();
    $invitation->delete();

    $this->get($link)->assertNotFound();
    $this->post($link, acceptInput())->assertNotFound();
});

test('a link only works on its own workspace', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    $acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    URL::forceRootUrl(rtrim($acme->url(), '/'));
    $invitation = $acme->execute(fn () => WorkspaceInvitation::factory()->create(['email' => 'sam@example.com']));
    $link = $invitation->acceptUrl();

    expect($link)->toStartWith($acme->url());
    $this->get($link)->assertOk();

    $replayed = str_replace('//acme.', '//other.', $link);
    $this->get($replayed)->assertNotFound();
    $this->post($replayed, acceptInput())->assertNotFound();

    expect(User::where('email', 'sam@example.com')->exists())->toBeFalse()
        ->and($other->users()->count())->toBe(0)
        ->and(WorkspaceInvitation::withoutGlobalScopes()->count())->toBe(1);
});
