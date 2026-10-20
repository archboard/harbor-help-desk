<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\BulkUpdateTickets;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\DeleteTicket;
use App\Actions\Tickets\RestoreTicket;
use App\Actions\Tickets\SearchTickets;
use App\Actions\Tickets\UpdateTicket;
use App\Data\Desks\DeskData;
use App\Data\Desks\DeskMemberData;
use App\Data\SavedViews\SavedViewData;
use App\Data\Tags\TagData;
use App\Data\Tickets\AttachmentData;
use App\Data\Tickets\TicketCommentData;
use App\Data\Tickets\TicketData;
use App\Data\Tickets\TicketEventData;
use App\Data\Tickets\TicketReminderData;
use App\Data\Users\UserData;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Desk;
use App\Models\SavedView;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * The tickets the user can see, filtered by the query string and loaded a page at a time
     * as the list scrolls.
     */
    public function index(Request $request, SearchTickets $searchTickets): Response
    {
        $filters = $request->except('page');
        $desks = Desk::visibleTo($request->user())->orderBy('name')->get();
        $tickets = $searchTickets->handle($request->user(), $filters)
            ->with(['desk', 'requester', 'assignees', 'tags'])
            ->paginate(50);

        return Inertia::render('tickets/Index', [
            'tickets' => Inertia::scroll(TicketData::collect($tickets)),
            'filters' => (object) $filters,
            'savedViews' => SavedViewData::collect(SavedView::visibleTo($request->user())->with('user')->orderBy('name')->get()),
            'desks' => DeskData::collect($desks),
            'leadDeskIds' => $desks->filter(fn (Desk $desk): bool => $request->user()->can('update', $desk))->values()->modelKeys(),
            'agents' => UserData::collect(Workspace::current()->users()->orderBy('name')->get()),
            'tags' => TagData::collect(Tag::query()->orderBy('name')->get()),
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
        ]);
    }

    /**
     * The new ticket form: each desk the user can open tickets on, with its fields and members.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('tickets/Create', [
            'desks' => DeskData::collect(Desk::visibleTo($request->user())
                ->with(['customFields', 'users' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get()),
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
        ]);
    }

    public function store(Request $request, Desk $desk, CreateTicket $createTicket): RedirectResponse
    {
        $ticket = $createTicket->handle($request->user(), $desk, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket #:number created.', ['number' => $ticket->number])]);

        return to_route('tickets.show', $ticket);
    }

    /**
     * Apply one change to many tickets from the list.
     */
    public function bulk(Request $request, BulkUpdateTickets $bulkUpdateTickets): RedirectResponse
    {
        $count = $bulkUpdateTickets->handle($request->user(), $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count ticket updated.|:count tickets updated.', $count)]);

        return back();
    }

    /**
     * Events that the thread already shows as comments.
     */
    protected const array EVENTS_SHOWN_AS_COMMENTS = [
        TicketEventType::COMMENT_ADDED,
        TicketEventType::INTERNAL_NOTE_ADDED,
    ];

    public function show(Request $request, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['desk.customFields', 'requester', 'assignees', 'tags']);

        return Inertia::render('tickets/Show', [
            'ticket' => TicketData::from($ticket),
            'comments' => TicketCommentData::collect($ticket->comments()->with(['author', 'attachments'])->get()),
            'events' => TicketEventData::collect($ticket->events()
                ->with('actor')
                ->whereNotIn('type', self::EVENTS_SHOWN_AS_COMMENTS)
                ->get()),
            'attachments' => AttachmentData::collect($ticket->attachments()->whereNull('ticket_comment_id')->oldest()->get()),
            'reminders' => TicketReminderData::collect($ticket->reminders()
                ->visibleTo($request->user())
                ->whereNull('sent_at')
                ->orderBy('remind_at')
                ->get()),
            'deskMembers' => DeskMemberData::collect($ticket->desk->users()->orderBy('name')->get()),
            'desks' => DeskData::collect(Desk::visibleTo($request->user())->orderBy('name')->get()),
            'tags' => TagData::collect(Tag::query()->orderBy('name')->get()),
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
            'can' => [
                'update' => $request->user()->can('update', $ticket),
                'delete' => $request->user()->can('delete', $ticket),
                'restore' => $request->user()->can('restore', $ticket),
            ],
        ]);
    }

    public function update(Request $request, Ticket $ticket, UpdateTicket $updateTicket): RedirectResponse
    {
        $updateTicket->handle($request->user(), $ticket, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket updated.')]);

        return back();
    }

    public function destroy(Request $request, Ticket $ticket, DeleteTicket $deleteTicket): RedirectResponse
    {
        $deleteTicket->handle($request->user(), $ticket);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket deleted.')]);

        return to_route('tickets.index');
    }

    public function restore(Request $request, Ticket $ticket, RestoreTicket $restoreTicket): RedirectResponse
    {
        $restoreTicket->handle($request->user(), $ticket);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket restored.')]);

        return back();
    }
}
