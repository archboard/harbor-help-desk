<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\SyncTicketAssignees;
use App\Data\Users\UserData;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TicketAssigneeController extends Controller
{
    /**
     * Replace the ticket's assignees with `user_ids`.
     *
     * @return Collection<int, UserData>
     */
    public function update(Request $request, #[CurrentUser] User $user, Ticket $ticket, SyncTicketAssignees $syncTicketAssignees): Collection
    {
        $syncTicketAssignees->handle($user, $ticket, $request->all());

        return UserData::collect($ticket->assignees()->orderBy('name')->get());
    }
}
