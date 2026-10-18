<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateWorkspace
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Workspace $workspace, array $input): Workspace
    {
        Gate::forUser($actor)->authorize('update', $workspace);

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
        ])->validate();

        $workspace->update($validated);

        return $workspace;
    }
}
