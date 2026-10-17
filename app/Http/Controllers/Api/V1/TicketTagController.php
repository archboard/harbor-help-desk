<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\SyncTicketTags;
use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketTagController extends Controller
{
    /**
     * Replace the ticket's tags with `names`, creating tags that don't exist yet.
     */
    public function update(Request $request, Ticket $ticket, SyncTicketTags $syncTicketTags): AnonymousResourceCollection
    {
        $syncTicketTags->handle($request->user(), $ticket, $request->all());

        return TagResource::collection($ticket->tags()->orderBy('name')->get());
    }
}
