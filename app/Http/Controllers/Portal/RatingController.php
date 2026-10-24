<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Tickets\RateTicket;
use App\Data\Portal\PortalTicketData;
use App\Enums\SatisfactionScore;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RatingController extends Controller
{
    /**
     * The survey page with the emailed choice preselected. Nothing is recorded until it is submitted, so
     * a mail scanner opening the link rates nothing.
     */
    public function show(Ticket $ticket, SatisfactionScore $rating): Response
    {
        return Inertia::render('portal/Rate', [
            'ticket' => PortalTicketData::from($ticket->load(['desk', 'satisfactionRating'])),
            'rating' => $rating,
            'options' => SatisfactionScore::options(),
        ]);
    }

    /**
     * Rate the ticket as its requester, on the same signed link.
     */
    public function store(Request $request, Ticket $ticket, RateTicket $rateTicket): RedirectResponse
    {
        $rateTicket->handle($ticket->requester, $ticket, $request->only(['rating', 'comment']));

        Flash::success(__('Thanks for your feedback.'));

        return back();
    }
}
