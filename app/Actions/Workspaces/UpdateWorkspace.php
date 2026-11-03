<?php

namespace App\Actions\Workspaces;

use App\Data\Workspaces\UpdateWorkspaceData;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;

class UpdateWorkspace
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Workspace $workspace, array $input): Workspace
    {
        Gate::forUser($actor)->authorize('update', $workspace);

        $changes = UpdateWorkspaceData::validateAndCreate($input)->filled();

        $workspace->update($changes);

        AuditEvent::record(AuditAction::WORKSPACE_UPDATED, $actor, $workspace, ['changed' => array_keys($changes)]);

        return $workspace;
    }
}
