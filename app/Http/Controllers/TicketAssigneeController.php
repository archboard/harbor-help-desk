<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\SyncTicketAssignees;
use App\Http\Flash;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketAssigneeController extends Controller
{
    /**
     * Replace the ticket's assignees with `user_ids`.
     */
    public function update(Request $request, Ticket $ticket, SyncTicketAssignees $syncTicketAssignees): RedirectResponse
    {
        $syncTicketAssignees->handle($request->user(), $ticket, $request->all());

        Flash::success(__('Assignees updated.'));

        return back();
    }
}
