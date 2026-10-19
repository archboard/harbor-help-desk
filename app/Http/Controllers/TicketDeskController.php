<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\MoveTicket;
use App\Models\Desk;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketDeskController extends Controller
{
    /**
     * Move the ticket to another desk.
     */
    public function update(Request $request, Ticket $ticket, MoveTicket $moveTicket): RedirectResponse
    {
        $request->validate(['desk_id' => ['required', 'string']]);

        $moveTicket->handle($request->user(), $ticket, Desk::findOrFail($request->string('desk_id')->value()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket moved.')]);

        return back();
    }
}
