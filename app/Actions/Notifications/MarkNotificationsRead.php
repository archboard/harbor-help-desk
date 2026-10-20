<?php

namespace App\Actions\Notifications;

use App\Data\Notifications\MarkNotificationsReadData;
use App\Models\User;

class MarkNotificationsRead
{
    /**
     * Mark the user's unread notifications in the current workspace as read: the given `ids`,
     * or all of them when `ids` is null. Ids that aren't the user's are ignored.
     *
     * @param  array<string, mixed>  $input
     * @return int The number of notifications marked.
     */
    public function handle(User $user, array $input = []): int
    {
        $data = MarkNotificationsReadData::validateAndCreate($input);

        return $user->notifications()
            ->reorder()
            ->whereNull('read_at')
            ->when($data->ids !== null, fn ($query) => $query->whereKey($data->ids))
            ->update(['read_at' => now()]);
    }
}
