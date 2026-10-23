<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\LookupTickets;
use App\Data\Tickets\TicketData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LookupTicketsController extends Controller
{
    /**
     * JSON for ticket pickers.
     *
     * @return Collection<int, TicketData>
     */
    public function __invoke(Request $request, LookupTickets $lookupTickets): Collection
    {
        return TicketData::collect($lookupTickets->handle($request->user(), $request->only('search')));
    }
}
