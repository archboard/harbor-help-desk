<?php

use App\Actions\Workspaces\AcceptInvitation;
use App\Actions\Workspaces\InviteMember;
use App\Actions\Workspaces\ResendInvitation;
use App\Actions\Workspaces\RevokeInvitation;
use App\Enums\AuditAction;
use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\AuditEvent;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

function memberWithRole(WorkspaceRole $role, ?Workspace $workspace = null, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    ($workspace ?? Workspace::current())->users()->attach($user, ['role' => $role]);

    return $user;
}

test('an admin invites someone and they are emailed the accept link', function () {
    Notification::fake();
    $admin = memberWithRole(WorkspaceRole::ADMIN);
    $desk = Desk::sole();

    $invitation = app(InviteMember::class)->handle($admin, ['email' => 'Sam@Example.com', 'role' => 'agent', 'desk_id' => $desk->id]);

    expect($invitation)->email->toBe('sam@example.com')->role->toBe(WorkspaceRole::AGENT)
        ->desk_id->toBe($desk->id)->invited_by_id->toBe($admin->id);

    $audit = AuditEvent::where('action', AuditAction::MEMBER_INVITED)->sole();
    expect($audit)->subject_id->toBe($invitation->id)->actor_id->toBe($admin->id);

    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class, function ($notification, $channels, $notifiable) use ($invitation, $admin) {
        $mail = $notification->toMail(new AnonymousNotifiable);

        expect($notifiable->routes['mail'])->toBe('sam@example.com')
            ->and($mail->subject)->toBe($admin->name.' invited you to '.$this->workspace->name)
            ->and($mail->actionUrl)->toBe($invitation->acceptUrl())
            ->and(implode(' ', $mail->outroLines))->toContain($invitation->acceptExpiresAt()->toFormattedDayDateString())
            ->and(implode(' ', $mail->introLines))->toContain('Agent');

        return true;
    });
});

test('inviting rejects bad input', function (array $input, array $errors) {
    $admin = memberWithRole(WorkspaceRole::ADMIN);

    expect(fn () => app(InviteMember::class)->handle($admin, $input))
        ->toThrow(fn (ValidationException $e) => expect(array_keys($e->errors()))->toBe($errors));

    expect(WorkspaceInvitation::count())->toBe(0);
})->with([
    'bad email' => [['email' => 'nope', 'role' => 'agent'], ['email']],
    'owner role' => [['email' => 'a@example.com', 'role' => 'owner'], ['role']],
    'unknown desk' => [['email' => 'a@example.com', 'role' => 'agent', 'desk_id' => 'dsk_missing'], ['desk_id']],
    'several at once' => [['email' => 'nope', 'role' => 'owner', 'desk_id' => 'dsk_missing'], ['email', 'role', 'desk_id']],
]);

test('inviting rejects a desk from another workspace', function () {
    $admin = memberWithRole(WorkspaceRole::ADMIN);
    $foreign = Workspace::factory()->create()->execute(fn () => Desk::factory()->create());

    expect(fn () => app(InviteMember::class)->handle($admin, ['email' => 'a@example.com', 'role' => 'agent', 'desk_id' => $foreign->id]))
        ->toThrow(ValidationException::class);
});

test('inviting rejects existing members and pending invitations case-insensitively', function () {
    $admin = memberWithRole(WorkspaceRole::ADMIN);
    memberWithRole(WorkspaceRole::AGENT, attributes: ['email' => 'Member@Example.com']);
    WorkspaceInvitation::factory()->create(['email' => 'pending@example.com']);

    foreach (['MEMBER@example.com', 'Pending@Example.com'] as $email) {
        expect(fn () => app(InviteMember::class)->handle($admin, ['email' => $email, 'role' => 'agent']))
            ->toThrow(ValidationException::class);
    }

    expect(WorkspaceInvitation::count())->toBe(1);
});

test('an agent cannot invite, revoke or resend', function () {
    Notification::fake();
    $agent = memberWithRole(WorkspaceRole::AGENT);
    $invitation = WorkspaceInvitation::factory()->create();

    expect(fn () => app(InviteMember::class)->handle($agent, ['email' => 'a@example.com', 'role' => 'agent']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RevokeInvitation::class)->handle($agent, $invitation))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ResendInvitation::class)->handle($agent, $invitation))->toThrow(AuthorizationException::class);

    expect(WorkspaceInvitation::count())->toBe(1);
    Notification::assertNothingSent();
});

test('revoking deletes the invitation and its link stops working', function () {
    $admin = memberWithRole(WorkspaceRole::ADMIN);
    $invitation = WorkspaceInvitation::factory()->create();
    $url = $invitation->acceptUrl();

    app(RevokeInvitation::class)->handle($admin, $invitation);

    expect(WorkspaceInvitation::count())->toBe(0)
        ->and(AuditEvent::where('action', AuditAction::INVITATION_REVOKED)->sole())->subject_id->toBe($invitation->id);

    $this->get($url)->assertNotFound();
});

test('resending refreshes the expiry and emails again', function () {
    Notification::fake();
    $admin = memberWithRole(WorkspaceRole::ADMIN);
    $invitation = WorkspaceInvitation::factory()->create(['created_at' => now()->subDays(5)]);

    app(ResendInvitation::class)->handle($admin, $invitation);

    expect($invitation->fresh()->acceptExpiresAt()->diffInDays(now(), absolute: true))->toBeLessThan(7.01)->toBeGreaterThan(6.99)
        ->and(AuditEvent::where('action', AuditAction::INVITATION_RESENT)->sole())->subject_id->toBe($invitation->id);

    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === $invitation->email);
});

test("one workspace's admin cannot manage another's invitations, and duplicates are per workspace", function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    Notification::fake();

    $invitation = WorkspaceInvitation::factory()->create(['email' => 'sam@example.com']);
    memberWithRole(WorkspaceRole::AGENT, attributes: ['email' => 'lee@example.com']);

    $other = Workspace::factory()->create(['subdomain' => 'other'])->makeCurrent();
    $otherAdmin = memberWithRole(WorkspaceRole::ADMIN, $other);

    expect(fn () => app(RevokeInvitation::class)->handle($otherAdmin, $invitation))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ResendInvitation::class)->handle($otherAdmin, $invitation))->toThrow(AuthorizationException::class);

    foreach (['sam@example.com', 'lee@example.com'] as $email) {
        app(InviteMember::class)->handle($otherAdmin, ['email' => $email, 'role' => 'agent']);
    }

    expect(WorkspaceInvitation::count())->toBe(2);
    $this->workspace->makeCurrent();
    expect(WorkspaceInvitation::count())->toBe(1)->and($invitation->fresh())->not->toBeNull();
});
