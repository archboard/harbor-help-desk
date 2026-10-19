<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\SearchTickets;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Resources\DeskResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\TicketResource;
use App\Http\Resources\UserResource;
use App\Models\Desk;
use App\Models\Tag;
use App\Models\Workspace;
use Illuminate\Http\Request;
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
        $tickets = $searchTickets->handle($request->user(), $filters)
            ->with(['desk', 'requester', 'assignees', 'tags'])
            ->paginate(50);

        return Inertia::render('tickets/Index', [
            'tickets' => Inertia::scroll(TicketResource::collection($tickets)),
            'filters' => (object) $filters,
            'desks' => DeskResource::collection(Desk::visibleTo($request->user())->orderBy('name')->get()),
            'agents' => UserResource::collection(Workspace::current()->users()->orderBy('name')->get()),
            'tags' => TagResource::collection(Tag::query()->orderBy('name')->get()),
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
        ]);
    }
}
