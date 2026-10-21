<?php

namespace App\Actions\Workspaces;

use App\Data\Workspaces\SetAwayData;
use App\Models\User;
use App\Models\Workspace;

class SetAway
{
    /**
     * Mark the user away, or back, in the current workspace.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $user, array $input): void
    {
        $workspace = Workspace::current();
        $user->membership($workspace) ?? abort(403);
        $data = SetAwayData::validateAndCreate($input);

        $user->workspaces()->updateExistingPivot($workspace->getKey(), [
            'away_at' => $data->away ? now() : null,
        ]);
    }
}
