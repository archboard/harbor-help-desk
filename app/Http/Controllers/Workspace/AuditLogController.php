<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Audit\ListAuditEvents;
use App\Actions\Audit\ListWorkspaceTicketEvents;
use App\Data\Audit\AuditEventData;
use App\Data\Tickets\TicketEventData;
use App\Data\Users\UserData;
use App\Enums\AuditAction;
use App\Enums\TicketEventType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * The audit log: admin activity, or with `tab=tickets` the workspace's ticket activity,
     * filtered by the query string. Only the open tab's events are loaded.
     */
    public function index(Request $request, #[CurrentUser] User $user, ListAuditEvents $listAuditEvents, ListWorkspaceTicketEvents $listTicketEvents): Response
    {
        $tickets = $request->query('tab') === 'tickets';

        $events = $tickets
            ? $listTicketEvents->handle($user, $request->query())->through(TicketEventData::fromModel(...))
            : $listAuditEvents->handle($user, $request->query())->through(AuditEventData::fromModel(...));

        return Inertia::render('workspace/AuditLog', [
            'tab' => $tickets ? 'tickets' : 'admin',
            'events' => Inertia::scroll($events),
            'actions' => AuditAction::options(),
            'ticketEventTypes' => array_column(TicketEventType::cases(), 'value'),
            'members' => UserData::collect(Workspace::current()->users()->orderBy('name')->get()),
        ]);
    }
}
