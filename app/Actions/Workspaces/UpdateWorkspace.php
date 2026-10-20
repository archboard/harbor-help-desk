<?php

namespace App\Actions\Workspaces;

use App\Data\Workspaces\UpdateWorkspaceData;
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

        $workspace->update(UpdateWorkspaceData::validateAndCreate($input)->filled());

        return $workspace;
    }
}
