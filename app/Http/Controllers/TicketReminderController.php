<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\CancelTicketReminder;
use App\Actions\Tickets\SetTicketReminder;
use App\Http\Flash;
use App\Models\Ticket;
use App\Models\TicketReminder;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketReminderController extends Controller
{
    public function store(Request $request, #[CurrentUser] User $user, Ticket $ticket, SetTicketReminder $setTicketReminder): RedirectResponse
    {
        $setTicketReminder->handle($user, $ticket, $request->all());

        Flash::success(__('Reminder set.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $user, TicketReminder $reminder, CancelTicketReminder $cancelTicketReminder): RedirectResponse
    {
        $cancelTicketReminder->handle($user, $reminder);

        Flash::success(__('Reminder cancelled.'));

        return back();
    }
}
