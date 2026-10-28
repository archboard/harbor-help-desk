<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\CancelTicketReminder;
use App\Actions\Tickets\SetTicketReminder;
use App\Data\Tickets\TicketReminderData;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReminder;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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
    public function index(#[CurrentUser] User $user, Ticket $ticket): Collection
    {
        Gate::authorize('view', $ticket);

        return TicketReminderData::collect(
            $ticket->reminders()->visibleTo($user)->whereNull('sent_at')->orderBy('remind_at')->get(),
        );
    }

    public function store(Request $request, #[CurrentUser] User $user, Ticket $ticket, SetTicketReminder $setTicketReminder): TicketReminderData
    {
        return TicketReminderData::from($setTicketReminder->handle($user, $ticket, $request->all()));
    }

    public function destroy(#[CurrentUser] User $user, TicketReminder $reminder, CancelTicketReminder $cancelTicketReminder): Response
    {
        $cancelTicketReminder->handle($user, $reminder);

        return response()->noContent();
    }
}
