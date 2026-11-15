<?php

use App\Enums\TicketStatus;
use App\Enums\WorkspaceRole;
use App\Mcp\Servers\HarborServer;
use App\Mcp\Tools\InviteMemberTool;
use App\Mcp\Tools\ListInvitationsTool;
use App\Mcp\Tools\ListMembersTool;
use App\Mcp\Tools\RemoveMemberTool;
use App\Mcp\Tools\ResendInvitationTool;
use App\Mcp\Tools\RevokeInvitationTool;
use App\Mcp\Tools\UpdateMemberRoleTool;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->admin = User::factory()->create(['name' => 'Ada Admin']);
    $this->acme->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->agent = User::factory()->create(['name' => 'Andy Agent']);
    $this->acme->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
});

function asAdmin(string $tool, array $arguments = [])
{
    return HarborServer::actingAs(test()->admin)->tool($tool, $arguments);
}

/**
 * @return array{workspace: Workspace, admin: User}
 */
function otherWorkspaceWithAdmin(): array
{
    $other = Workspace::factory()->create(['subdomain' => 'other'])->makeCurrent();
    $admin = User::factory()->create(['name' => 'Olive Other']);
    $other->users()->attach($admin, ['role' => WorkspaceRole::ADMIN]);

    return ['workspace' => $other, 'admin' => $admin];
}

test('list_members pages through the members', function () {
    asAdmin(ListMembersTool::class)
        ->assertOk()->assertSee(['Ada Admin', 'Andy Agent', '"total":2', '"page":1', '"next_page":null']);

    User::factory()->count(25)->create()->each(fn (User $user) => $this->acme->users()->attach($user, ['role' => WorkspaceRole::AGENT]));

    asAdmin(ListMembersTool::class)->assertSee(['"total":27', '"next_page":2']);
    asAdmin(ListMembersTool::class, ['page' => 2])->assertSee(['"page":2', '"next_page":null']);
});

test('list_invitations shows pending invitations with desk and inviter', function () {
    $desk = Desk::sole();
    WorkspaceInvitation::factory()->create(['email' => 'new@example.com', 'desk_id' => $desk->id, 'invited_by_id' => $this->admin->id]);

    asAdmin(ListInvitationsTool::class)
        ->assertOk()->assertSee(['new@example.com', $desk->name, 'Ada Admin', '"total":1', '"next_page":null']);
});

test('invite_member creates the invitation, emails it and rejects duplicates', function () {
    Notification::fake();

    asAdmin(InviteMemberTool::class, ['email' => 'New@Example.com', 'role' => 'agent'])
        ->assertOk()->assertSee('new@example.com');

    $invitation = WorkspaceInvitation::sole();
    expect($invitation->invited_by_id)->toBe($this->admin->id);
    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);

    asAdmin(InviteMemberTool::class, ['email' => 'new@example.com', 'role' => 'agent'])->assertHasErrors();
    asAdmin(InviteMemberTool::class, ['email' => $this->agent->email, 'role' => 'agent'])->assertHasErrors();
    expect(WorkspaceInvitation::count())->toBe(1);
});

test('revoke_invitation deletes the invitation', function () {
    $invitation = WorkspaceInvitation::factory()->create();

    asAdmin(RevokeInvitationTool::class, ['invitation' => $invitation->id])->assertOk()->assertSee($invitation->id);

    expect(WorkspaceInvitation::count())->toBe(0);
});

test('resend_invitation emails the invitation again', function () {
    Notification::fake();
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'again@example.com']);

    asAdmin(ResendInvitationTool::class, ['invitation' => $invitation->id])->assertOk()->assertSee('again@example.com');

    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);
});

test('update_member_role changes the role and validates it', function () {
    asAdmin(UpdateMemberRoleTool::class, ['member' => $this->agent->id, 'role' => 'admin'])
        ->assertOk()->assertSee(['Andy Agent', '"role":"admin"']);
    expect($this->agent->workspaceRole($this->acme))->toBe(WorkspaceRole::ADMIN);

    asAdmin(UpdateMemberRoleTool::class, ['member' => $this->agent->id, 'role' => 'bogus'])->assertHasErrors();
    asAdmin(UpdateMemberRoleTool::class, ['member' => $this->agent->id, 'role' => 'owner'])->assertHasErrors();
    expect($this->agent->workspaceRole($this->acme))->toBe(WorkspaceRole::ADMIN);
});

test('remove_member removes the member and unassigns their open tickets', function () {
    $ticket = Ticket::factory()->create(['desk_id' => Desk::sole()->id, 'status' => TicketStatus::OPEN]);
    $ticket->assignees()->attach($this->agent, ['workspace_id' => $this->acme->id]);

    asAdmin(RemoveMemberTool::class, ['member' => $this->agent->id])->assertOk()->assertSee($this->agent->id);

    expect($this->acme->users()->whereKey($this->agent->id)->exists())->toBeFalse()
        ->and($ticket->assignees()->count())->toBe(0);
});

test('an agent is refused on every member tool', function () {
    $invitation = WorkspaceInvitation::factory()->create();
    $calls = [
        [ListMembersTool::class, []],
        [ListInvitationsTool::class, []],
        [InviteMemberTool::class, ['email' => 'x@example.com', 'role' => 'agent']],
        [RevokeInvitationTool::class, ['invitation' => $invitation->id]],
        [ResendInvitationTool::class, ['invitation' => $invitation->id]],
        [UpdateMemberRoleTool::class, ['member' => $this->admin->id, 'role' => 'agent']],
        [RemoveMemberTool::class, ['member' => $this->admin->id]],
    ];

    foreach ($calls as [$tool, $arguments]) {
        HarborServer::actingAs($this->agent)->tool($tool, $arguments)->assertHasErrors();
    }

    expect(WorkspaceInvitation::count())->toBe(1)
        ->and($this->admin->workspaceRole($this->acme))->toBe(WorkspaceRole::ADMIN);
});

test('unknown invitation and member ids are not found', function () {
    asAdmin(RevokeInvitationTool::class, ['invitation' => 'inv_missing'])->assertHasErrors(['Invitation inv_missing not found.']);
    asAdmin(ResendInvitationTool::class, ['invitation' => 'inv_missing'])->assertHasErrors(['Invitation inv_missing not found.']);
    asAdmin(UpdateMemberRoleTool::class, ['member' => 'usr_missing', 'role' => 'agent'])->assertHasErrors(['Member usr_missing not found.']);
    asAdmin(RemoveMemberTool::class, ['member' => 'usr_missing'])->assertHasErrors(['Member usr_missing not found.']);
});

test('workspace B admin cannot see or change workspace A members and invitations', function () {
    Notification::fake();
    $invitation = WorkspaceInvitation::factory()->create(['email' => 'secret@example.com']);
    ['workspace' => $other, 'admin' => $otherAdmin] = otherWorkspaceWithAdmin();

    $asOther = fn (string $tool, array $arguments = []) => HarborServer::actingAs($otherAdmin)->tool($tool, $arguments);

    $asOther(ListMembersTool::class)->assertOk()->assertSee('Olive Other')->assertDontSee(['Ada Admin', 'Andy Agent']);
    $asOther(ListInvitationsTool::class)->assertOk()->assertDontSee('secret@example.com');
    $asOther(RevokeInvitationTool::class, ['invitation' => $invitation->id])->assertHasErrors(["Invitation {$invitation->id} not found."]);
    $asOther(ResendInvitationTool::class, ['invitation' => $invitation->id])->assertHasErrors(["Invitation {$invitation->id} not found."]);
    $asOther(UpdateMemberRoleTool::class, ['member' => $this->agent->id, 'role' => 'admin'])->assertHasErrors(["Member {$this->agent->id} not found."]);
    $asOther(RemoveMemberTool::class, ['member' => $this->agent->id])->assertHasErrors(["Member {$this->agent->id} not found."]);

    Notification::assertNothingSent();
    $this->acme->makeCurrent();
    expect(WorkspaceInvitation::whereKey($invitation->id)->exists())->toBeTrue()
        ->and($this->agent->workspaceRole($this->acme))->toBe(WorkspaceRole::AGENT)
        ->and($this->acme->users()->count())->toBe(2)
        ->and($other->users()->count())->toBe(1);
});
