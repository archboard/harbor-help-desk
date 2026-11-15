<?php

namespace App\Actions\Workspaces;

use App\Actions\Tickets\SyncTicketAssignees;
use App\Enums\AuditAction;
use App\Enums\TicketStatus;
use App\Enums\WorkspaceRole;
use App\Models\AuditEvent;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;

class RemoveMember
{
    public function __construct(protected SyncTicketAssignees $syncTicketAssignees) {}

    /**
     * Remove a member from the current workspace, or let them leave it when `$actor` is
     * `$member`. Only owners remove owners, and the last owner can't go. Their ticket
     * assignments (all but closed tickets), desk memberships and workspace-scoped API tokens are
     * dropped. The workspace row is locked, so two owners can't leave at once. A user who isn't a
     * member is not found.
     *
     * Tickets are unassigned as Harbor, not as `$actor`: the ticket policy would refuse an
     * agent who has since left the ticket's desk, and `$actor` is already authorized here.
     */
    public function handle(User $actor, User $member): void
    {
        $workspace = Workspace::current();
        $leaving = $actor->is($member);

        if (! $leaving) {
            Gate::forUser($actor)->authorize('manageMembers', $workspace);
        }

        DB::transaction(function () use ($actor, $member, $workspace, $leaving): void {
            Workspace::query()->whereKey($workspace->getKey())->lockForUpdate()->sole();

            $role = $workspace->users()->whereKey($member->getKey())->first()?->pivot->role
                ?? throw (new ModelNotFoundException)->setModel(User::class, [$member->getKey()]);

            if ($role === WorkspaceRole::OWNER) {
                if (! $leaving && $actor->workspaceRole($workspace) !== WorkspaceRole::OWNER) {
                    throw ValidationException::withMessages(['user' => __('Only an owner can remove an owner.')]);
                }

                if ($workspace->users()->wherePivot('role', WorkspaceRole::OWNER)->count() < 2) {
                    throw ValidationException::withMessages(['user' => __('The workspace needs at least one owner.')]);
                }
            }

            Ticket::query()
                ->where('status', '!=', TicketStatus::CLOSED)
                ->whereHas('assignees', fn ($query) => $query->whereKey($member->getKey()))
                ->with('assignees')
                ->each(function (Ticket $ticket) use ($member): void {
                    $this->syncTicketAssignees->handle(null, $ticket, [
                        'user_ids' => array_values(array_diff($ticket->assignees->modelKeys(), [$member->getKey()])),
                    ]);
                });

            DB::table('desk_user')->where('workspace_id', $workspace->getKey())->where('user_id', $member->getKey())->delete();
            $workspace->users()->detach($member->getKey());

            // A token with several workspace scopes is revoked for all of them, since its scopes can't be edited.
            $member->tokens()->where('revoked', false)->get()
                ->filter(fn ($token): bool => in_array($workspace->tokenScope(), $token->scopes, true))
                ->each(function ($token): void {
                    $token->revoke();
                    Passport::refreshToken()->where('access_token_id', $token->getKey())->update(['revoked' => true]);
                });

            AuditEvent::record($leaving ? AuditAction::MEMBER_LEFT : AuditAction::MEMBER_REMOVED, $actor, $member, ['user_id' => $member->getKey()]);
        });
    }
}
