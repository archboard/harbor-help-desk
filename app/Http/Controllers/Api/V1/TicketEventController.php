<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Tickets\TicketEventData;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;

class TicketEventController extends Controller
{
    /**
     * The ticket's audit trail, oldest first.
     *
     * @return PaginatedDataCollection<int, TicketEventData>
     */
    public function index(Ticket $ticket): PaginatedDataCollection
    {
        Gate::authorize('view', $ticket);

        return TicketEventData::collect($ticket->events()->with('actor')->paginate(100), PaginatedDataCollection::class);
    }
}
