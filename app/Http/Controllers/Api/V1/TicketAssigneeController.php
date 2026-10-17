<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\SyncTicketAssignees;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketAssigneeController extends Controller
{
    /**
     * Replace the ticket's assignees with `user_ids`.
     */
    public function update(Request $request, Ticket $ticket, SyncTicketAssignees $syncTicketAssignees): AnonymousResourceCollection
    {
        $syncTicketAssignees->handle($request->user(), $ticket, $request->all());

        return UserResource::collection($ticket->assignees()->orderBy('name')->get());
    }
}
