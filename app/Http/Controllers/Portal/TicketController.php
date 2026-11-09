<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\OpenPortalTicket;
use App\Actions\Tickets\PageTicketThread;
use App\Data\Portal\PortalDeskData;
use App\Data\Portal\PortalTicketData;
use App\Enums\SatisfactionScore;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\Desk;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * The contact's tickets across all desks, newest activity first, optionally only the open or the resolved ones.
     */
    public function index(Request $request): Response
    {
        $filter = in_array($request->query('status'), ['open', 'resolved'], true) ? $request->query('status') : null;
        $resolved = collect(TicketStatus::cases())->filter->isResolved()->all();

        $tickets = Auth::guard('contact')->user()->tickets()
            ->with('desk')
            ->when($filter === 'open', fn ($query) => $query->whereNotIn('status', $resolved))
            ->when($filter === 'resolved', fn ($query) => $query->whereIn('status', $resolved))
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('portal/tickets/Index', [
            'tickets' => Inertia::scroll(PortalTicketData::collect($tickets)),
            'filter' => $filter,
        ]);
    }

    /**
     * The ticket with its public comments, their files and authors. Internal notes and events are never shown.
     */
    public function show(string $ticket, PageTicketThread $pageTicketThread): Response
    {
        $ticket = Auth::guard('contact')->user()->tickets()->with(['desk', 'satisfactionRating'])->findOrFail($ticket);

        $thread = $pageTicketThread->handle($ticket, [], publicOnly: true, limit: 25);

        return Inertia::render('portal/tickets/Show', [
            'ticket' => PortalTicketData::from($ticket),
            'canRate' => Workspace::current()->settings->surveysEnabled && $ticket->status->isResolved(),
            'options' => SatisfactionScore::options(),
            'comments' => $thread->comments,
            'earlier_before' => $thread->earlierBefore,
        ]);
    }

    /**
     * The new ticket form, with the active desks to pick from. Archived desks are left out.
     */
    public function create(): Response
    {
        return Inertia::render('portal/tickets/Create', [
            'desks' => PortalDeskData::collect(Desk::query()->orderBy('name')->get()),
        ]);
    }

    /**
     * Open a ticket on the desk as the signed-in contact.
     */
    public function store(Request $request, Desk $desk, OpenPortalTicket $openPortalTicket): RedirectResponse
    {
        $ticket = $openPortalTicket->handle(Auth::guard('contact')->user(), $desk, $request->only(['subject', 'body', 'attachments']));

        Flash::success(__('Ticket opened.'));

        return to_route('portal.tickets.show', $ticket);
    }
}
