<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\LookupTickets;
use App\Data\Tickets\TicketData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LookupTicketsController extends Controller
{
    /**
     * JSON for ticket pickers.
     *
     * @return Collection<int, TicketData>
     */
    public function __invoke(Request $request, #[CurrentUser] User $user, LookupTickets $lookupTickets): Collection
    {
        return TicketData::collect($lookupTickets->handle($user, $request->only('search')));
    }
}
