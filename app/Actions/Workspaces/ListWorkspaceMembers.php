<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Gate;

class ListWorkspaceMembers
{
    /**
     * The current workspace's members by name, each with a count of their desks. Owners and
     * admins only.
     *
     * @return BelongsToMany<User, Workspace, WorkspaceUser, 'pivot'>
     */
    public function handle(User $actor): BelongsToMany
    {
        $workspace = Workspace::current();

        Gate::forUser($actor)->authorize('manageMembers', $workspace);

        return $workspace->users()->withCount('desks')->orderBy('name')->orderBy('users.id');
    }
}
