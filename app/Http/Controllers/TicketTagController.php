<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\SyncTicketTags;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketTagController extends Controller
{
    /**
     * Replace the ticket's tags with `names`, creating tags that don't exist yet.
     */
    public function update(Request $request, Ticket $ticket, SyncTicketTags $syncTicketTags): RedirectResponse
    {
        $syncTicketTags->handle($request->user(), $ticket, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tags updated.')]);

        return back();
    }
}
