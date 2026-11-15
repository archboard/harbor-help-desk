<?php

namespace App\Actions\Workspaces;

use App\Data\Workspaces\InviteMemberData;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class InviteMember
{
    /**
     * Invite an email address to the current workspace as an admin or agent, optionally to a
     * desk, and email them a link. The address can't already be a member or have a pending
     * invitation, compared case-insensitively. The email is sent after the invitation is saved.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): WorkspaceInvitation
    {
        $workspace = Workspace::current();

        Gate::forUser($actor)->authorize('manageMembers', $workspace);

        $data = InviteMemberData::validateAndCreateWith($input, [
            'desk_id' => [Rule::exists('desks', 'id')->where('workspace_id', $workspace->getKey())->whereNull('archived_at')],
            'email' => [
                $this->unless(fn (string $email): bool => $workspace->users()->whereRaw('lower(users.email) = ?', [$email])->exists(), __('This person is already a member of the workspace.')),
                $this->unless(fn (string $email): bool => WorkspaceInvitation::query()->whereRaw('lower(email) = ?', [$email])->exists(), __('This email address has already been invited.')),
            ],
        ]);

        $invitation = DB::transaction(function () use ($actor, $data): WorkspaceInvitation {
            $invitation = WorkspaceInvitation::create([
                ...$data->filled(),
                'email' => mb_strtolower($data->email),
                'invited_by_id' => $actor->getKey(),
            ]);

            AuditEvent::record(AuditAction::MEMBER_INVITED, $actor, $invitation, ['email' => $invitation->email, 'role' => $invitation->role->value]);

            return $invitation;
        });

        Notification::route('mail', $invitation->email)->notify(new WorkspaceInvitationNotification($invitation));

        return $invitation;
    }

    /**
     * A rule that fails with `$message` when `$exists` is true for the lowercased email.
     *
     * @param  Closure(string): bool  $exists
     */
    protected function unless(Closure $exists, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($exists, $message): void {
            if (is_string($value) && $exists(mb_strtolower($value))) {
                $fail($message);
            }
        };
    }
}
