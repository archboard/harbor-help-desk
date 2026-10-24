<?php

namespace App\Http\Controllers\Portal;

use App\Data\Portal\PortalTicketData;
use App\Data\Tickets\TicketCommentData;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
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
    public function show(string $ticket): Response
    {
        $ticket = Auth::guard('contact')->user()->tickets()->with('desk')->findOrFail($ticket);

        return Inertia::render('portal/tickets/Show', [
            'ticket' => PortalTicketData::from($ticket),
            'comments' => TicketCommentData::collect($ticket->comments()
                ->where('is_internal', false)
                ->with(['author', 'attachments'])
                ->get()),
        ]);
    }
}
