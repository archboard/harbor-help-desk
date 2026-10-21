<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\ClaimNextTicket;
use App\Http\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketClaimController extends Controller
{
    public function __invoke(Request $request, ClaimNextTicket $claimNextTicket): RedirectResponse
    {
        $ticket = $claimNextTicket->handle($request->user());

        if ($ticket === null) {
            Flash::info(__('Nothing waiting.'));

            return back();
        }

        return to_route('tickets.show', $ticket);
    }
}
