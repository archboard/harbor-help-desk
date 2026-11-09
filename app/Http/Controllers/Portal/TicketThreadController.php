<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Tickets\PageTicketThread;
use App\Data\Tickets\TicketThreadData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketThreadController extends Controller
{
    /**
     * JSON for the "Load earlier" control: the page of public comments before the `before` comment.
     */
    public function __invoke(Request $request, string $ticket, PageTicketThread $pageTicketThread): TicketThreadData
    {
        $ticket = Auth::guard('contact')->user()->tickets()->findOrFail($ticket);

        return $pageTicketThread->handle($ticket, $request->only('before'), publicOnly: true, limit: 25);
    }
}
