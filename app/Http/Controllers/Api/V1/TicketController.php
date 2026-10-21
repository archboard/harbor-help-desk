<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\BulkUpdateTickets;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\DeleteTicket;
use App\Actions\Tickets\RestoreTicket;
use App\Actions\Tickets\SearchTickets;
use App\Actions\Tickets\UpdateTicket;
use App\Data\Tickets\TicketData;
use App\Enums\TicketSource;
use App\Http\Controllers\Controller;
use App\Models\Desk;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;

class TicketController extends Controller
{
    protected const array RELATIONS = ['requester', 'assignees', 'tags', 'slaTargets'];

    /**
     * The tickets the user can see, filtered by the query string.
     *
     * @return PaginatedDataCollection<int, TicketData>
     */
    public function index(Request $request, SearchTickets $searchTickets): PaginatedDataCollection
    {
        return TicketData::collect(
            $searchTickets->handle($request->user(), $request->query())->with(self::RELATIONS)->paginate(),
            PaginatedDataCollection::class,
        );
    }

    public function store(Request $request, Desk $desk, CreateTicket $createTicket): TicketData
    {
        return TicketData::from($createTicket->handle($request->user(), $desk, $request->all(), TicketSource::API)->load(self::RELATIONS));
    }

    /**
     * Apply one change to up to 100 tickets, all or nothing.
     *
     * @return array{updated: int}
     */
    public function bulk(Request $request, BulkUpdateTickets $bulkUpdateTickets): array
    {
        return ['updated' => $bulkUpdateTickets->handle($request->user(), $request->all())];
    }

    public function show(Ticket $ticket): TicketData
    {
        Gate::authorize('view', $ticket);

        return TicketData::from($ticket->load(self::RELATIONS));
    }

    public function update(Request $request, Ticket $ticket, UpdateTicket $updateTicket): TicketData
    {
        return TicketData::from($updateTicket->handle($request->user(), $ticket, $request->all())->load(self::RELATIONS));
    }

    public function destroy(Request $request, Ticket $ticket, DeleteTicket $deleteTicket): Response
    {
        $deleteTicket->handle($request->user(), $ticket);

        return response()->noContent();
    }

    public function restore(Request $request, Ticket $ticket, RestoreTicket $restoreTicket): JsonResponse
    {
        return response()->json(TicketData::from($restoreTicket->handle($request->user(), $ticket)->load(self::RELATIONS)));
    }
}
