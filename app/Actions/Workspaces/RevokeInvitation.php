<?php

namespace App\Actions\Workspaces;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RevokeInvitation
{
    /**
     * Withdraw an invitation. Its emailed link stops working, and a queued email still waiting is skipped.
     */
    public function handle(User $actor, WorkspaceInvitation $invitation): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $invitation->workspace);

        DB::transaction(function () use ($actor, $invitation): void {
            $invitation->delete();

            AuditEvent::record(AuditAction::INVITATION_REVOKED, $actor, $invitation, ['email' => $invitation->email], $invitation->workspace_id);
        });
    }
}
