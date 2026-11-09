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
use App\Data\SavedViews\SavedViewData;
use App\Data\Tags\TagData;
use App\Data\Tickets\TicketData;
use App\Data\Users\UserData;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Http\Flash;
use App\Http\TicketPageProps;
use App\Models\CustomField;
use App\Models\Desk;
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
            'customFields' => CustomFieldData::collect(CustomField::query()->orderBy('position')->get()),
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

    public function show(#[CurrentUser] User $user, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        return Inertia::render('tickets/Show', TicketPageProps::handle($user, $ticket, deferMacros: true));
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
