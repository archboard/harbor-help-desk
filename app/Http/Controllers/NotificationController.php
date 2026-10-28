<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\ListNotifications;
use App\Data\Notifications\NotificationData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Spatie\LaravelData\PaginatedDataCollection;

class NotificationController extends Controller
{
    /**
     * JSON for the notification dropdown.
     *
     * @return PaginatedDataCollection<int, NotificationData>
     */
    public function index(#[CurrentUser] User $user, ListNotifications $listNotifications): PaginatedDataCollection
    {
        return NotificationData::collect($listNotifications->handle($user, 15), PaginatedDataCollection::class);
    }
}
