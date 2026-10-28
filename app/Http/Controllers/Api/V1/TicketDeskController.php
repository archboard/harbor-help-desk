<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\MoveTicket;
use App\Data\Tickets\TicketData;
use App\Http\Controllers\Controller;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

class TicketDeskController extends Controller
{
    /**
     * Move the ticket to the desk in `desk_id`. An unknown or archived desk is a 404.
     */
    public function update(Request $request, #[CurrentUser] User $user, Ticket $ticket, MoveTicket $moveTicket): TicketData
    {
        $request->validate(['desk_id' => ['required', 'string']]);

        return TicketData::from($moveTicket->handle($user, $ticket, Desk::findOrFail($request->string('desk_id')->value())));
    }
}
