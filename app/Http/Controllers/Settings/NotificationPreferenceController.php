<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Notifications\UpdateNotificationPreferences;
use App\Data\Notifications\NotificationPreferenceData;
use App\Enums\NotificationChannel;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    /**
     * Show the user's notification preferences in the current workspace.
     */
    public function edit(Request $request): Response
    {
        $workspace = Workspace::current();

        return Inertia::render('settings/Notifications', [
            'preferences' => NotificationPreferenceData::forMembership($request->user()->membership($workspace) ?? abort(403)),
            'channels' => NotificationChannel::options(),
            'hasNotificationMailbox' => $workspace->notificationMailbox() !== null,
        ]);
    }

    public function update(Request $request, UpdateNotificationPreferences $updateNotificationPreferences): RedirectResponse
    {
        $updateNotificationPreferences->handle($request->user(), $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification preferences saved.')]);

        return back();
    }
}
