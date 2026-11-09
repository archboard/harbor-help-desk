<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\BulkUpdateTickets;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\DeleteTicket;
use App\Actions\Tickets\RestoreTicket;
use App\Actions\Tickets\SearchTickets;
use App\Actions\Tickets\UpdateTicket;
use App\Data\CustomFields\CustomFieldData;
use App\Data\Desks\DeskData;
use App\Data\Desks\DeskMemberData;
use App\Data\Macros\MacroData;
use App\Data\SavedViews\SavedViewData;
use App\Data\Tags\TagData;
use App\Data\Tickets\AttachmentData;
use App\Data\Tickets\TicketCommentData;
use App\Data\Tickets\TicketData;
use App\Data\Tickets\TicketEventData;
use App\Data\Tickets\TicketReminderData;
use App\Data\Users\UserData;
use App\Enums\CustomFieldType;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Http\Flash;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\SavedView;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
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
    public function index(Request $request, #[CurrentUser] User $user, SearchTickets $searchTickets): Response
    {
        $filters = $request->except(['page', 'preview']);
        $desks = Desk::visibleTo($user)->orderBy('name')->get();
        $tickets = $searchTickets->handle($user, $filters)
            ->with(['desk', 'requester', 'assignees', 'tags'])
            ->paginate(50);

        return Inertia::render('tickets/Index', [
            'tickets' => Inertia::scroll(TicketData::collect($tickets)),
            'filters' => (object) $filters,
            'savedViews' => SavedViewData::collect(SavedView::visibleTo($user)->with('user')->orderBy('name')->get()),
            'desks' => DeskData::collect($desks),
            'leadDeskIds' => $desks->filter(fn (Desk $desk): bool => $user->can('update', $desk))->values()->modelKeys(),
            'agents' => UserData::collect(Workspace::current()->users()->orderBy('name')->get()),
            'tags' => TagData::collect(Tag::query()->orderBy('name')->get()),
            'customFields' => CustomFieldData::collect(CustomField::query()
                ->whereIn('type', [CustomFieldType::SELECT, CustomFieldType::MULTI_SELECT, CustomFieldType::CHECKBOX])
                ->orderBy('position')
                ->get()),
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
        ]);
    }

    /**
     * The new ticket form: each desk the user can open tickets on, with its fields and members.
     */
    public function create(#[CurrentUser] User $user): Response
    {
        return Inertia::render('tickets/Create', [
            'desks' => DeskData::collect(Desk::visibleTo($user)
                ->with(['customFields', 'users' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get()),
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user, Desk $desk, CreateTicket $createTicket): RedirectResponse
    {
        $ticket = $createTicket->handle($user, $desk, $request->all(), TicketSource::WEB);

        Flash::success(__('Ticket #:number created.', ['number' => $ticket->number]));

        return to_route('tickets.show', $ticket);
    }

    /**
     * Apply one change to many tickets from the list.
     */
    public function bulk(Request $request, #[CurrentUser] User $user, BulkUpdateTickets $bulkUpdateTickets): RedirectResponse
    {
        $count = $bulkUpdateTickets->handle($user, $request->all());

        Flash::success(trans_choice(':count ticket updated.|:count tickets updated.', $count));

        return back();
    }

    /**
     * Events that the thread already shows as comments.
     */
    protected const array EVENTS_SHOWN_AS_COMMENTS = [
        TicketEventType::COMMENT_ADDED,
        TicketEventType::INTERNAL_NOTE_ADDED,
    ];

    public function show(#[CurrentUser] User $user, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['desk.customFields', 'requester', 'assignees', 'tags', 'slaTargets', 'satisfactionRating']);

        return Inertia::render('tickets/Show', [
            'ticket' => TicketData::from($ticket),
            'comments' => TicketCommentData::collect($ticket->comments()->with(['author', 'attachments'])->get()),
            'events' => TicketEventData::collect($ticket->events()
                ->with('actor')
                ->whereNotIn('type', self::EVENTS_SHOWN_AS_COMMENTS)
                ->get()),
            'attachments' => AttachmentData::collect($ticket->attachments()->whereNull('ticket_comment_id')->oldest()->get()),
            'reminders' => TicketReminderData::collect($ticket->reminders()
                ->visibleTo($user)
                ->whereNull('sent_at')
                ->orderBy('remind_at')
                ->get()),
            'deskMembers' => DeskMemberData::collect($ticket->desk->users()->orderBy('name')->get()),
            'desks' => DeskData::collect(Desk::visibleTo($user)->orderBy('name')->get()),
            'tags' => TagData::collect(Tag::query()->orderBy('name')->get()),
            'macros' => Inertia::defer(fn () => MacroData::collect(Macro::visibleTo($user)->usableOn($ticket)->orderBy('name')->get())),
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
            'can' => [
                'update' => $user->can('update', $ticket),
                'delete' => $user->can('delete', $ticket),
                'restore' => $user->can('restore', $ticket),
            ],
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $user, Ticket $ticket, UpdateTicket $updateTicket): RedirectResponse
    {
        $updateTicket->handle($user, $ticket, $request->all());

        Flash::success(__('Ticket updated.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $user, Ticket $ticket, DeleteTicket $deleteTicket): RedirectResponse
    {
        $deleteTicket->handle($user, $ticket);

        Flash::success(__('Ticket deleted.'));

        return to_route('tickets.index');
    }

    public function restore(#[CurrentUser] User $user, Ticket $ticket, RestoreTicket $restoreTicket): RedirectResponse
    {
        $restoreTicket->handle($user, $ticket);

        Flash::success(__('Ticket restored.'));

        return back();
    }
}
