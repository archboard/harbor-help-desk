<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\CancelTicketReminder;
use App\Actions\Tickets\SetTicketReminder;
use App\Models\Ticket;
use App\Models\TicketReminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketReminderController extends Controller
{
    public function store(Request $request, Ticket $ticket, SetTicketReminder $setTicketReminder): RedirectResponse
    {
        $setTicketReminder->handle($request->user(), $ticket, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder set.')]);

        return back();
    }

    public function destroy(Request $request, TicketReminder $reminder, CancelTicketReminder $cancelTicketReminder): RedirectResponse
    {
        $cancelTicketReminder->handle($request->user(), $reminder);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder cancelled.')]);

        return back();
    }
}
