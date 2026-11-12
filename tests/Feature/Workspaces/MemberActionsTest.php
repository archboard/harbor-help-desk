<?php

use App\Actions\Workspaces\RemoveMember;
use App\Actions\Workspaces\UpdateMemberRole;
use App\Enums\AuditAction;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Enums\WorkspaceRole;
use App\Models\AuditEvent;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->owner = enrolledAs($this->workspace, WorkspaceRole::OWNER);
    $this->admin = enrolledAs($this->workspace, WorkspaceRole::ADMIN);
});

function enrolledAs(Workspace $workspace, WorkspaceRole $role): User
{
    $user = User::factory()->create();
    $workspace->users()->attach($user, ['role' => $role]);

    return $user;
}

function ticketAssignedTo(User $user, TicketStatus $status = TicketStatus::OPEN): Ticket
{
    $ticket = Ticket::factory()->create(['desk_id' => Desk::sole()->id, 'status' => $status]);
    $ticket->assignees()->attach($user, ['workspace_id' => $ticket->workspace_id]);

    return $ticket;
}

test('an admin changes an agent to admin and back', function () {
    app(UpdateMemberRole::class)->handle($this->admin, $this->agent, ['role' => 'admin']);

    expect($this->agent->workspaceRole($this->workspace))->toBe(WorkspaceRole::ADMIN);

    app(UpdateMemberRole::class)->handle($this->admin, $this->agent, ['role' => 'agent']);

    expect($this->agent->workspaceRole($this->workspace))->toBe(WorkspaceRole::AGENT);

    $audit = AuditEvent::where('action', AuditAction::MEMBER_ROLE_CHANGED)->latest('id')->first();
    expect($audit->properties)->toEqual(['user_id' => $this->agent->id, 'from' => 'admin', 'to' => 'agent']);
});

test('an admin can neither grant owner nor change an owner', function () {
    expect(fn () => app(UpdateMemberRole::class)->handle($this->admin, $this->agent, ['role' => 'owner']))
        ->toThrow(ValidationException::class);
    expect(fn () => app(UpdateMemberRole::class)->handle($this->admin, $this->owner, ['role' => 'agent']))
        ->toThrow(ValidationException::class);

    expect($this->agent->workspaceRole($this->workspace))->toBe(WorkspaceRole::AGENT)
        ->and($this->owner->workspaceRole($this->workspace))->toBe(WorkspaceRole::OWNER);
});

test('an owner grants owner', function () {
    app(UpdateMemberRole::class)->handle($this->owner, $this->agent, ['role' => 'owner']);

    expect($this->agent->workspaceRole($this->workspace))->toBe(WorkspaceRole::OWNER);
});

test('the last owner cannot be demoted, but one of two can', function () {
    expect(fn () => app(UpdateMemberRole::class)->handle($this->owner, $this->owner, ['role' => 'admin']))
        ->toThrow(ValidationException::class);

    $second = enrolledAs($this->workspace, WorkspaceRole::OWNER);
    app(UpdateMemberRole::class)->handle($this->owner, $second, ['role' => 'admin']);

    expect($second->workspaceRole($this->workspace))->toBe(WorkspaceRole::ADMIN);
});

test('an agent cannot change roles or remove others', function () {
    expect(fn () => app(UpdateMemberRole::class)->handle($this->agent, $this->admin, ['role' => 'agent']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(RemoveMember::class)->handle($this->agent, $this->admin))
        ->toThrow(AuthorizationException::class);
});

test('a non-member is not found', function () {
    $stranger = User::factory()->create();

    expect(fn () => app(UpdateMemberRole::class)->handle($this->admin, $stranger, ['role' => 'admin']))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => app(RemoveMember::class)->handle($this->admin, $stranger))
        ->toThrow(ModelNotFoundException::class);
});

test('removing a member unassigns open tickets, leaves closed ones, and drops memberships and tokens', function () {
    $other = Workspace::factory()->create();
    $this->workspace->makeCurrent();
    $open = ticketAssignedTo($this->agent);
    $closed = ticketAssignedTo($this->agent, TicketStatus::CLOSED);
    $this->agent->createToken('Here', [$this->workspace->tokenScope()]);
    $keep = $this->agent->createToken('There', [$other->tokenScope()])->token;
    $revoked = $this->agent->createToken('Here', [$this->workspace->tokenScope()])->token;

    app(RemoveMember::class)->handle($this->admin, $this->agent);

    expect($open->assignees()->count())->toBe(0)
        ->and($closed->assignees()->count())->toBe(1)
        ->and(TicketEvent::where('ticket_id', $open->id)->where('type', TicketEventType::ASSIGNEE_REMOVED)->count())->toBe(1)
        ->and(DB::table('desk_user')->where('user_id', $this->agent->id)->count())->toBe(0)
        ->and($this->agent->workspaceRole($this->workspace))->toBeNull()
        ->and($revoked->fresh()->revoked)->toBeTrue()
        ->and($keep->fresh()->revoked)->toBeFalse()
        ->and($this->agent->tokens()->where('revoked', false)->count())->toBe(1);

    $audit = AuditEvent::where('action', AuditAction::MEMBER_REMOVED)->sole();
    expect($audit->properties)->toBe(['user_id' => $this->agent->id])->and($audit->actor_id)->toBe($this->admin->id);
});

test('removing a member keeps a stale assignee on the same ticket', function () {
    $stale = deskMember($this->workspace, Desk::sole());
    $ticket = ticketAssignedTo($this->agent);
    $ticket->assignees()->attach($stale, ['workspace_id' => $ticket->workspace_id]);
    Desk::sole()->users()->detach($stale);

    app(RemoveMember::class)->handle($this->admin, $this->agent);

    expect($ticket->assignees()->pluck('users.id')->all())->toBe([$stale->id]);
});

test('an agent can leave', function () {
    app(RemoveMember::class)->handle($this->agent, $this->agent);

    expect($this->agent->workspaceRole($this->workspace))->toBeNull()
        ->and(AuditEvent::where('action', AuditAction::MEMBER_LEFT)->count())->toBe(1);
});

test('the last owner cannot leave or be removed', function () {
    expect(fn () => app(RemoveMember::class)->handle($this->owner, $this->owner))->toThrow(ValidationException::class);
    expect(fn () => app(RemoveMember::class)->handle($this->admin, $this->owner))->toThrow(ValidationException::class);

    expect($this->owner->workspaceRole($this->workspace))->toBe(WorkspaceRole::OWNER);
});

test('an admin cannot remove an owner, but an owner can remove another owner', function () {
    $second = enrolledAs($this->workspace, WorkspaceRole::OWNER);

    expect(fn () => app(RemoveMember::class)->handle($this->admin, $second))->toThrow(ValidationException::class);

    app(RemoveMember::class)->handle($this->owner, $second);

    expect($second->workspaceRole($this->workspace))->toBeNull();
});

test("another workspace's admin cannot change or remove a member who is only in this one", function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $b = Workspace::factory()->create();
    $bAdmin = enrolledAs($b, WorkspaceRole::ADMIN);
    $b->makeCurrent();

    expect(fn () => app(UpdateMemberRole::class)->handle($bAdmin, $this->agent, ['role' => 'admin']))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => app(RemoveMember::class)->handle($bAdmin, $this->agent))
        ->toThrow(ModelNotFoundException::class);

    expect($this->agent->workspaceRole($this->workspace))->toBe(WorkspaceRole::AGENT);
});

test('removal from one workspace leaves the same user untouched in another', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $b = Workspace::factory()->create();
    $b->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
    $bDesk = $b->execute(fn () => Desk::sole());
    $bDesk->users()->attach($this->agent, ['role' => 'agent', 'workspace_id' => $b->id]);
    $bTicket = $b->execute(fn () => ticketAssignedTo($this->agent));
    $bToken = $this->agent->createToken('B', [$b->tokenScope()])->token;
    $aToken = $this->agent->createToken('A', [$this->workspace->tokenScope()])->token;

    $this->workspace->makeCurrent();
    app(RemoveMember::class)->handle($this->admin, $this->agent);

    expect($this->agent->workspaceRole($this->workspace))->toBeNull()
        ->and($this->agent->workspaceRole($b))->toBe(WorkspaceRole::AGENT)
        ->and(DB::table('desk_user')->where('user_id', $this->agent->id)->where('workspace_id', $b->id)->count())->toBe(1)
        ->and(DB::table('ticket_user')->where('ticket_id', $bTicket->id)->count())->toBe(1)
        ->and($bToken->fresh()->revoked)->toBeFalse()
        ->and($aToken->fresh()->revoked)->toBeTrue();
});
