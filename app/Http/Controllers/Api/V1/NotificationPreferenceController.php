<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notifications\UpdateNotificationPreferences;
use App\Data\Notifications\NotificationPreferenceData;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * @return array<int, NotificationPreferenceData>
     */
    public function show(Request $request): array
    {
        return NotificationPreferenceData::forMembership($request->user()->membership(Workspace::current()) ?? abort(403));
    }

    /**
     * @return array<int, NotificationPreferenceData>
     */
    public function update(Request $request, UpdateNotificationPreferences $updateNotificationPreferences): array
    {
        return NotificationPreferenceData::forMembership($updateNotificationPreferences->handle($request->user(), $request->all()));
    }
}
