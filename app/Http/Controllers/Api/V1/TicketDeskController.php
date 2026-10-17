<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\MoveTicket;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Desk;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketDeskController extends Controller
{
    /**
     * Move the ticket to the desk in `desk_id`. An unknown or archived desk is a 404.
     */
    public function update(Request $request, Ticket $ticket, MoveTicket $moveTicket): TicketResource
    {
        $request->validate(['desk_id' => ['required', 'string']]);

        return new TicketResource($moveTicket->handle($request->user(), $ticket, Desk::findOrFail($request->string('desk_id')->value())));
    }
}
