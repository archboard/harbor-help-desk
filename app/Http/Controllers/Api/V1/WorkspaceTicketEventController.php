<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Audit\ListWorkspaceTicketEvents;
use App\Data\Audit\ListWorkspaceTicketEventsData;
use App\Data\Tickets\TicketEventData;
use App\Http\Attributes\ApiQuery;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Spatie\LaravelData\CursorPaginatedDataCollection;

class WorkspaceTicketEventController extends Controller
{
    /**
     * Ticket activity across the workspace, newest first. Admins only.
     *
     * @return CursorPaginatedDataCollection<int, TicketEventData>
     */
    #[ApiQuery(ListWorkspaceTicketEventsData::class)]
    public function index(Request $request, #[CurrentUser] User $user, ListWorkspaceTicketEvents $listTicketEvents): CursorPaginatedDataCollection
    {
        return TicketEventData::collect($listTicketEvents->handle($user, $request->query()), CursorPaginatedDataCollection::class);
    }
}
