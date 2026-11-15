<?php

namespace App\Actions\Workspaces;

use App\Data\Workspaces\UpdateMemberRoleData;
use App\Enums\AuditAction;
use App\Enums\WorkspaceRole;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateMemberRole
{
    /**
     * Change a member's role in the current workspace. Admins can set admin or agent. Only
     * owners can grant owner or change an owner, and the workspace keeps at least one owner. The
     * workspace row is locked, so two people can't demote the last two owners at once. A user who
     * isn't a member is not found.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, User $member, array $input): void
    {
        $workspace = Workspace::current();

        Gate::forUser($actor)->authorize('manageMembers', $workspace);

        $actorRole = $actor->workspaceRole($workspace);

        $data = UpdateMemberRoleData::validateAndCreateWith($input, [
            'role' => [function (string $attribute, mixed $value, Closure $fail) use ($actorRole): void {
                if ($value === WorkspaceRole::OWNER->value && $actorRole !== WorkspaceRole::OWNER) {
                    $fail(__('Only an owner can make someone an owner.'));
                }
            }],
        ]);

        DB::transaction(function () use ($actor, $actorRole, $member, $workspace, $data): void {
            Workspace::query()->whereKey($workspace->getKey())->lockForUpdate()->sole();

            $current = $workspace->users()->whereKey($member->getKey())->first()?->pivot->role
                ?? throw (new ModelNotFoundException)->setModel(User::class, [$member->getKey()]);

            if ($current === $data->role) {
                return;
            }

            if ($current === WorkspaceRole::OWNER) {
                if ($actorRole !== WorkspaceRole::OWNER) {
                    throw ValidationException::withMessages(['role' => __('Only an owner can change an owner’s role.')]);
                }

                if ($workspace->users()->wherePivot('role', WorkspaceRole::OWNER)->count() < 2) {
                    throw ValidationException::withMessages(['role' => __('The workspace needs at least one owner.')]);
                }
            }

            $workspace->users()->updateExistingPivot($member->getKey(), ['role' => $data->role]);

            AuditEvent::record(AuditAction::MEMBER_ROLE_CHANGED, $actor, $member, [
                'user_id' => $member->getKey(),
                'from' => $current->value,
                'to' => $data->role->value,
            ]);
        });
    }
}
