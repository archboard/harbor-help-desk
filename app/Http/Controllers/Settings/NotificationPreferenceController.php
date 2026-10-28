<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Notifications\UpdateNotificationPreferences;
use App\Data\Notifications\NotificationPreferenceData;
use App\Enums\NotificationChannel;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    /**
     * Show the user's notification preferences in the current workspace.
     */
    public function edit(#[CurrentUser] User $user): Response
    {
        $workspace = Workspace::current();

        return Inertia::render('settings/Notifications', [
            'preferences' => NotificationPreferenceData::forMembership($user->membership($workspace) ?? abort(403)),
            'channels' => NotificationChannel::options(),
            'hasNotificationMailbox' => $workspace->notificationMailbox() !== null,
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateNotificationPreferences $updateNotificationPreferences): RedirectResponse
    {
        $updateNotificationPreferences->handle($user, $request->all());

        Flash::success(__('Notification preferences saved.'));

        return back();
    }
}
