<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\MoveTicket;
use App\Http\Flash;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketDeskController extends Controller
{
    /**
     * Move the ticket to another desk.
     */
    public function update(Request $request, #[CurrentUser] User $user, Ticket $ticket, MoveTicket $moveTicket): RedirectResponse
    {
        $request->validate(['desk_id' => ['required', 'string']]);

        $moveTicket->handle($user, $ticket, Desk::findOrFail($request->string('desk_id')->value()));

        Flash::success(__('Ticket moved.'));

        return back();
    }
}
