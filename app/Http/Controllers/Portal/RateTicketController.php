<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Tickets\RateTicket;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RateTicketController extends Controller
{
    /**
     * Rate one of the signed-in contact's tickets.
     */
    public function __invoke(Request $request, string $ticket, RateTicket $rateTicket): RedirectResponse
    {
        $contact = Auth::guard('contact')->user();

        $rateTicket->handle($contact, $contact->tickets()->findOrFail($ticket), $request->only(['rating', 'comment']));

        Flash::success(__('Thanks for your feedback.'));

        return back();
    }
}
