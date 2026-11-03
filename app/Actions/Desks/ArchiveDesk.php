<?php

namespace App\Actions\Desks;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ArchiveDesk
{
    /**
     * Archive a desk, refusing to archive the workspace's last active desk.
     */
    public function handle(User $actor, Desk $desk): void
    {
        Gate::forUser($actor)->authorize('delete', $desk);

        DB::transaction(function () use ($actor, $desk): void {
            // Lock the workspace so two concurrent archives can't remove the last two desks.
            Workspace::query()->whereKey($desk->workspace_id)->lockForUpdate()->first();

            if (Desk::query()->where('workspace_id', $desk->workspace_id)->count() <= 1) {
                throw ValidationException::withMessages([
                    'desk' => __('A workspace needs at least one active desk.'),
                ]);
            }

            $desk->delete();

            AuditEvent::record(AuditAction::DESK_ARCHIVED, $actor, $desk);
        });
    }
}
