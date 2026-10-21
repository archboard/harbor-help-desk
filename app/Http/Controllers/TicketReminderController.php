<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\CancelTicketReminder;
use App\Actions\Tickets\SetTicketReminder;
use App\Http\Flash;
use App\Models\Ticket;
use App\Models\TicketReminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketReminderController extends Controller
{
    public function store(Request $request, Ticket $ticket, SetTicketReminder $setTicketReminder): RedirectResponse
    {
        $setTicketReminder->handle($request->user(), $ticket, $request->all());

        Flash::success(__('Reminder set.'));

        return back();
    }

    public function destroy(Request $request, TicketReminder $reminder, CancelTicketReminder $cancelTicketReminder): RedirectResponse
    {
        $cancelTicketReminder->handle($request->user(), $reminder);

        Flash::success(__('Reminder cancelled.'));

        return back();
    }
}
