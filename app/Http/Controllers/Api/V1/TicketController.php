<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\BulkUpdateTickets;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\DeleteTicket;
use App\Actions\Tickets\RestoreTicket;
use App\Actions\Tickets\SearchTickets;
use App\Actions\Tickets\UpdateTicket;
use App\Data\Tickets\BulkUpdateTicketsData;
use App\Data\Tickets\CreateTicketData;
use App\Data\Tickets\SearchTicketsData;
use App\Data\Tickets\TicketData;
use App\Data\Tickets\UpdateTicketData;
use App\Enums\TicketSource;
use App\Http\Attributes\ApiBody;
use App\Http\Attributes\ApiQuery;
use App\Http\Attributes\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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
    #[ApiQuery(SearchTicketsData::class)]
    public function index(Request $request, #[CurrentUser] User $user, SearchTickets $searchTickets): PaginatedDataCollection
    {
        return TicketData::collect(
            $searchTickets->handle($user, $request->query())->with(self::RELATIONS)->paginate(),
            PaginatedDataCollection::class,
        );
    }

    #[ApiBody(CreateTicketData::class)]
    public function store(Request $request, #[CurrentUser] User $user, Desk $desk, CreateTicket $createTicket): TicketData
    {
        return TicketData::from($createTicket->handle($user, $desk, $request->all(), TicketSource::API)->load(self::RELATIONS));
    }

    /**
     * Apply one change to up to 100 tickets, all or nothing.
     *
     * @return array{updated: int}
     */
    #[ApiBody(BulkUpdateTicketsData::class)]
    #[ApiResponse(schema: ['type' => 'object', 'properties' => ['updated' => ['type' => 'integer']], 'required' => ['updated']])]
    public function bulk(Request $request, #[CurrentUser] User $user, BulkUpdateTickets $bulkUpdateTickets): array
    {
        return ['updated' => $bulkUpdateTickets->handle($user, $request->all())];
    }

    public function show(Ticket $ticket): TicketData
    {
        Gate::authorize('view', $ticket);

        return TicketData::from($ticket->load([...self::RELATIONS, 'satisfactionRating']));
    }

    #[ApiBody(UpdateTicketData::class)]
    public function update(Request $request, #[CurrentUser] User $user, Ticket $ticket, UpdateTicket $updateTicket): TicketData
    {
        return TicketData::from($updateTicket->handle($user, $ticket, $request->all())->load(self::RELATIONS));
    }

    public function destroy(#[CurrentUser] User $user, Ticket $ticket, DeleteTicket $deleteTicket): Response
    {
        $deleteTicket->handle($user, $ticket);

        return response()->noContent();
    }

    #[ApiBody]
    #[ApiResponse(TicketData::class)]
    public function restore(#[CurrentUser] User $user, Ticket $ticket, RestoreTicket $restoreTicket): JsonResponse
    {
        return response()->json(TicketData::from($restoreTicket->handle($user, $ticket)->load(self::RELATIONS)));
    }
}
