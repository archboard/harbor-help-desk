<?php

namespace App\Actions\Workspaces;

use App\Data\Workspaces\AcceptInvitationData;
use App\Enums\AuditAction;
use App\Enums\DeskRole;
use App\Models\AuditEvent;
use App\Models\Desk;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    /**
     * Join the workspace the invitation is for. A signed-in `$user` must own the invited address;
     * with no user, a new verified account is created from `$input`, since the emailed link proves
     * the address. A member keeps the role they have. The invitation row is locked, so it can only
     * be accepted once.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(WorkspaceInvitation $invitation, ?User $user, array $input): User
    {
        return DB::transaction(function () use ($invitation, $user, $input): User {
            $invitation = WorkspaceInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();
            $workspace = $invitation->workspace;

            if ($user === null) {
                if (User::query()->whereRaw('lower(email) = ?', [mb_strtolower($invitation->email)])->exists()) {
                    throw ValidationException::withMessages(['email' => __('An account with this email already exists. Sign in to accept the invitation.')]);
                }

                $data = AcceptInvitationData::validateAndCreate($input);
                $user = User::create([...$data->filled(), 'email' => $invitation->email, 'email_verified_at' => now()]);
            } elseif (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
                throw ValidationException::withMessages(['email' => __('This invitation was sent to a different email address.')]);
            }

            if ($user->workspaceRole($workspace) !== null) {
                $invitation->delete();

                return $user;
            }

            $workspace->users()->attach($user, ['role' => $invitation->role]);

            // The workspace may not be current, which scopes the desk lookup.
            $workspace->execute(function () use ($invitation, $workspace, $user): void {
                $desk = $invitation->desk_id === null ? null : Desk::find($invitation->desk_id);
                $desk?->users()->attach($user, ['role' => DeskRole::AGENT, 'workspace_id' => $workspace->getKey()]);
            });

            $invitation->delete();

            AuditEvent::record(AuditAction::INVITATION_ACCEPTED, $user, $invitation, ['role' => $invitation->role->value, 'desk_id' => $invitation->desk_id], $workspace->getKey());

            return $user;
        });
    }
}
