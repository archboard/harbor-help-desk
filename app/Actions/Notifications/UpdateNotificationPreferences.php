<?php

namespace App\Actions\Notifications;

use App\Data\Notifications\UpdateNotificationPreferencesData;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;

class UpdateNotificationPreferences
{
    /**
     * Merge the choices into the user's preferences in the current workspace.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $user, array $input): WorkspaceUser
    {
        $workspace = Workspace::current();
        $membership = $user->membership($workspace) ?? abort(403);
        $data = UpdateNotificationPreferencesData::validateAndCreate($input);

        $user->workspaces()->updateExistingPivot($workspace->getKey(), [
            'notification_preferences' => array_replace_recursive($membership->notification_preferences, $data->preferences),
        ]);

        return $user->membership($workspace);
    }
}
