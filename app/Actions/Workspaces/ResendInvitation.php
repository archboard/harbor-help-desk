<?php

namespace App\Actions\Workspaces;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class ResendInvitation
{
    /**
     * Email the invitation again with a fresh 7-day link. The expiry counts from `created_at`, so
     * that is reset. Earlier emailed links keep their own, older expiry and still work until then.
     */
    public function handle(User $actor, WorkspaceInvitation $invitation): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $invitation->workspace);

        DB::transaction(function () use ($actor, $invitation): void {
            $invitation->forceFill(['created_at' => now()])->save();

            AuditEvent::record(AuditAction::INVITATION_RESENT, $actor, $invitation, ['email' => $invitation->email], $invitation->workspace_id);
        });

        Notification::route('mail', $invitation->email)->notify(new WorkspaceInvitationNotification($invitation));
    }
}
