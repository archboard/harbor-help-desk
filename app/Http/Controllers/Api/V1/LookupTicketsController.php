<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\LookupTickets;
use App\Data\Tickets\TicketData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LookupTicketsController extends Controller
{
    /**
     * A few tickets matching `search` (a number or part of a subject), for pickers.
     *
     * @return Collection<int, TicketData>
     */
    public function __invoke(Request $request, #[CurrentUser] User $user, LookupTickets $lookupTickets): Collection
    {
        return TicketData::collect($lookupTickets->handle($user, $request->only('search')));
    }
}
