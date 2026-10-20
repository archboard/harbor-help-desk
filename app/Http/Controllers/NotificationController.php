<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\ListNotifications;
use App\Data\Notifications\NotificationData;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class NotificationController extends Controller
{
    /**
     * JSON for the notification dropdown.
     *
     * @return PaginatedDataCollection<int, NotificationData>
     */
    public function index(Request $request, ListNotifications $listNotifications): PaginatedDataCollection
    {
        return NotificationData::collect($listNotifications->handle($request->user(), 15), PaginatedDataCollection::class);
    }
}
