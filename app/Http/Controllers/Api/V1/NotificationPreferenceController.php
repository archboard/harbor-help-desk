<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notifications\UpdateNotificationPreferences;
use App\Data\Notifications\NotificationPreferenceData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * @return array<int, NotificationPreferenceData>
     */
    public function show(#[CurrentUser] User $user): array
    {
        return NotificationPreferenceData::forMembership($user->membership(Workspace::current()) ?? abort(403));
    }

    /**
     * @return array<int, NotificationPreferenceData>
     */
    public function update(Request $request, #[CurrentUser] User $user, UpdateNotificationPreferences $updateNotificationPreferences): array
    {
        return NotificationPreferenceData::forMembership($updateNotificationPreferences->handle($user, $request->all()));
    }
}
