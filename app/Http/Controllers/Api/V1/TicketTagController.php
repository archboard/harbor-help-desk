<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\SyncTicketTags;
use App\Data\Tags\TagData;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TicketTagController extends Controller
{
    /**
     * Replace the ticket's tags with `names`, creating tags that don't exist yet.
     *
     * @return Collection<int, TagData>
     */
    public function update(Request $request, Ticket $ticket, SyncTicketTags $syncTicketTags): Collection
    {
        $syncTicketTags->handle($request->user(), $ticket, $request->all());

        return TagData::collect($ticket->tags()->orderBy('name')->get());
    }
}
