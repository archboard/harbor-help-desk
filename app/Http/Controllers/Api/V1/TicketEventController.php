<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketEventResource;
use App\Models\Ticket;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketEventController extends Controller
{
    /**
     * The ticket's audit trail, oldest first.
     */
    public function index(Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('view', $ticket);

        return TicketEventResource::collection($ticket->events()->with('actor')->paginate(100));
    }
}
