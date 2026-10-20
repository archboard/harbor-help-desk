<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\CancelTicketReminder;
use App\Actions\Tickets\SetTicketReminder;
use App\Data\Tickets\TicketReminderData;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReminder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class TicketReminderController extends Controller
{
    /**
     * The ticket's pending reminders the user can see: their own and the ticket-level ones.
     *
     * @return Collection<int, TicketReminderData>
     */
    public function index(Request $request, Ticket $ticket): Collection
    {
        Gate::authorize('view', $ticket);

        return TicketReminderData::collect(
            $ticket->reminders()->visibleTo($request->user())->whereNull('sent_at')->orderBy('remind_at')->get(),
        );
    }

    public function store(Request $request, Ticket $ticket, SetTicketReminder $setTicketReminder): TicketReminderData
    {
        return TicketReminderData::from($setTicketReminder->handle($request->user(), $ticket, $request->all()));
    }

    public function destroy(Request $request, TicketReminder $reminder, CancelTicketReminder $cancelTicketReminder): Response
    {
        $cancelTicketReminder->handle($request->user(), $reminder);

        return response()->noContent();
    }
}
