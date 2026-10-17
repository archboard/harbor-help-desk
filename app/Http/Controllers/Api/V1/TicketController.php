<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\DeleteTicket;
use App\Actions\Tickets\RestoreTicket;
use App\Actions\Tickets\SearchTickets;
use App\Actions\Tickets\UpdateTicket;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Desk;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    protected const array RELATIONS = ['requester', 'assignees', 'tags'];

    /**
     * The tickets the user can see, filtered by the query string.
     */
    public function index(Request $request, SearchTickets $searchTickets): AnonymousResourceCollection
    {
        return TicketResource::collection(
            $searchTickets->handle($request->user(), $request->query())->with(self::RELATIONS)->paginate(),
        );
    }

    public function store(Request $request, Desk $desk, CreateTicket $createTicket): TicketResource
    {
        return new TicketResource($createTicket->handle($request->user(), $desk, $request->all())->load(self::RELATIONS));
    }

    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        return new TicketResource($ticket->load(self::RELATIONS));
    }

    public function update(Request $request, Ticket $ticket, UpdateTicket $updateTicket): TicketResource
    {
        return new TicketResource($updateTicket->handle($request->user(), $ticket, $request->all())->load(self::RELATIONS));
    }

    public function destroy(Request $request, Ticket $ticket, DeleteTicket $deleteTicket): Response
    {
        $deleteTicket->handle($request->user(), $ticket);

        return response()->noContent();
    }

    public function restore(Request $request, Ticket $ticket, RestoreTicket $restoreTicket): TicketResource
    {
        return new TicketResource($restoreTicket->handle($request->user(), $ticket)->load(self::RELATIONS));
    }
}
