<?php

namespace App\Actions\Notifications;

use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListNotifications
{
    /**
     * The user's notifications in the current workspace, newest first.
     *
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function handle(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->notifications()->paginate($perPage);
    }
}
