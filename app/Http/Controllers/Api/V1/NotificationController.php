<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notifications\ListNotifications;
use App\Data\Notifications\NotificationData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Spatie\LaravelData\PaginatedDataCollection;

class NotificationController extends Controller
{
    /**
     * The user's notifications in the workspace, newest first.
     *
     * @return PaginatedDataCollection<int, NotificationData>
     */
    public function index(#[CurrentUser] User $user, ListNotifications $listNotifications): PaginatedDataCollection
    {
        return NotificationData::collect($listNotifications->handle($user), PaginatedDataCollection::class);
    }
}
