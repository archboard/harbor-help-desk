<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\SyncTicketAssignees;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketAssigneeController extends Controller
{
    /**
     * Replace the ticket's assignees with `user_ids`.
     */
    public function update(Request $request, Ticket $ticket, SyncTicketAssignees $syncTicketAssignees): RedirectResponse
    {
        $syncTicketAssignees->handle($request->user(), $ticket, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignees updated.')]);

        return back();
    }
}
