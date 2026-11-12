<?php

use App\Actions\Workspaces\AcceptInvitation;
use App\Enums\AuditAction;
use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\AuditEvent;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

/**
 * @return array<string, string>
 */
function newUserInput(array $overrides = []): array
{
    return [
        'name' => 'Sam New',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        ...$overrides,
    ];
}

test('a new person gets a verified account and joins with the invited role', function () {
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com', 'role' => WorkspaceRole::ADMIN]);

    $user = app(AcceptInvitation::class)->handle($invitation, null, newUserInput());

    expect($user)->name->toBe('Sam New')->email->toBe('sam@example.com')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('correct-horse-battery', $user->password))->toBeTrue()
        ->and($user->workspaceRole($this->workspace))->toBe(WorkspaceRole::ADMIN)
        ->and(WorkspaceInvitation::count())->toBe(0);

    $audit = AuditEvent::where('action', AuditAction::INVITATION_ACCEPTED)->sole();
    expect($audit)->subject_id->toBe($invitation->id)->actor_id->toBe($user->id);
});

test('a signed-in user joins without changing their account', function () {
    $user = User::factory()->create(['email' => 'Sam@Example.com']);
    $password = $user->password;
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com']);

    app(AcceptInvitation::class)->handle($invitation, $user, []);

    expect($user->fresh())->password->toBe($password)
        ->and($user->workspaceRole($this->workspace))->toBe(WorkspaceRole::AGENT)
        ->and(WorkspaceInvitation::count())->toBe(0);
});

test('a signed-in user with another email is rejected', function () {
    $user = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com']);

    expect(fn () => app(AcceptInvitation::class)->handle($invitation, $user, []))->toThrow(ValidationException::class);

    expect($user->workspaceRole($this->workspace))->toBeNull()
        ->and(WorkspaceInvitation::count())->toBe(1);
});

test('a guest cannot accept for an email that already has an account', function () {
    User::factory()->create(['email' => 'Sam@example.com']);
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com']);
    $users = User::count();

    expect(fn () => app(AcceptInvitation::class)->handle($invitation, null, newUserInput()))->toThrow(ValidationException::class);

    expect($this->workspace->users()->count())->toBe(0)
        ->and(User::count())->toBe($users)
        ->and(WorkspaceInvitation::count())->toBe(1);
});

test('an invited desk adds the user as an agent', function () {
    $desk = Desk::sole();
    $invitation = WorkspaceInvitation::factory()->forDesk($desk)->create();

    $user = app(AcceptInvitation::class)->handle($invitation, null, newUserInput());

    expect($desk->users()->sole())->id->toBe($user->id)->pivot->role->toBe(DeskRole::AGENT);
});

test('an existing member keeps their role and the invitation is consumed', function () {
    $member = User::factory()->create(['email' => 'sam@example.com']);
    $this->workspace->users()->attach($member, ['role' => WorkspaceRole::OWNER]);
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com', 'role' => WorkspaceRole::AGENT]);

    app(AcceptInvitation::class)->handle($invitation, $member, []);

    expect($member->workspaceRole($this->workspace))->toBe(WorkspaceRole::OWNER)
        ->and(WorkspaceInvitation::count())->toBe(0);
});

test('a new person must confirm a valid password', function () {
    $invitation = WorkspaceInvitation::factory()->create();
    $users = User::count();

    expect(fn () => app(AcceptInvitation::class)->handle($invitation, null, newUserInput(['password_confirmation' => 'nope'])))
        ->toThrow(ValidationException::class);

    expect(User::count())->toBe($users)->and(WorkspaceInvitation::count())->toBe(1);
});

test('an invitation that is already gone cannot be accepted again', function () {
    $invitation = WorkspaceInvitation::factory()->create();
    app(AcceptInvitation::class)->handle($invitation, null, newUserInput());

    expect(fn () => app(AcceptInvitation::class)->handle($invitation, null, newUserInput()))
        ->toThrow(ModelNotFoundException::class);
});

test("accepting one workspace's invitation does not touch another workspace", function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $invitation = WorkspaceInvitation::factory()->create();
    $other = Workspace::factory()->create(['subdomain' => 'other']);

    $user = app(AcceptInvitation::class)->handle($invitation, null, newUserInput());

    expect($user->workspaceRole($this->workspace))->not->toBeNull()
        ->and($user->workspaceRole($other))->toBeNull();

    $second = WorkspaceInvitation::factory()->create();
    $other->makeCurrent();

    expect(WorkspaceInvitation::find($second->id))->toBeNull();
});
