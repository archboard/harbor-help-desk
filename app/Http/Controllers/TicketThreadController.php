<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\PageTicketThread;
use App\Data\Tickets\TicketThreadData;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketThreadController extends Controller
{
    /**
     * JSON for the "Load earlier" control: the page of the thread before the `before` comment.
     */
    public function __invoke(Request $request, Ticket $ticket, PageTicketThread $pageTicketThread): TicketThreadData
    {
        Gate::authorize('view', $ticket);

        return $pageTicketThread->handle($ticket, $request->only('before'));
    }
}
