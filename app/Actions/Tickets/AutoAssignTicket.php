<?php

namespace App\Actions\Tickets;

use App\Enums\AssignmentStrategy;
use App\Enums\TicketStatus;
use App\Models\Automation;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AutoAssignTicket
{
    public function __construct(protected SyncTicketAssignees $syncTicketAssignees) {}

    /**
     * Assign an open, unassigned ticket to the next eligible member of its desk, acting as Harbor, or as the given actor.
     * Eligible members are in the desk's rotation and not Away in the workspace. Returns the new
     * assignee, or null when nothing was assigned. The desk row is locked so concurrent picks
     * can't choose the same member, and the ticket row so a concurrent claim can't also take it.
     *
     * The desk's strategy is the fallback: the automations job calls this without a strategy after the
     * automations have run, for new tickets and tickets moved onto a desk, so a ticket an automation
     * assigned is left alone. Automations can also call it with their own strategy.
     */
    public function handle(Ticket $ticket, ?AssignmentStrategy $strategy = null, User|Automation|null $actor = null): ?User
    {
        return DB::transaction(function () use ($ticket, $strategy, $actor): ?User {
            // Null when the desk is archived, which assigns nobody.
            $desk = Desk::query()->lockForUpdate()->find($ticket->desk_id);
            $strategy ??= $desk?->assignment_strategy;
            // Re-read under the lock, since a claim may have taken it since it was loaded.
            $ticket = Ticket::query()->lockForUpdate()->whereKey($ticket->getKey())->first();

            if ($desk === null
                || $ticket === null
                || $strategy === AssignmentStrategy::MANUAL
                || $ticket->status !== TicketStatus::OPEN
                || $ticket->assignees()->exists()) {
                return null;
            }

            $picker = $desk->users()
                ->wherePivot('in_rotation', true)
                ->whereHas('workspaces', fn (Builder $query) => $query
                    ->whereKey($ticket->workspace_id)
                    ->whereNull('workspace_user.away_at'));

            if ($strategy === AssignmentStrategy::LOAD_BALANCED) {
                $picker->orderBy(Ticket::query()
                    ->selectRaw('count(*)')
                    ->join('ticket_user', 'ticket_user.ticket_id', '=', 'tickets.id')
                    ->whereColumn('ticket_user.user_id', 'users.id')
                    ->where('tickets.desk_id', $desk->getKey())
                    ->whereIn('tickets.status', [TicketStatus::OPEN, TicketStatus::PENDING]));
            }

            $assignee = $picker
                ->orderByRaw('desk_user.last_assigned_at asc nulls first')
                ->orderBy('users.id')
                ->first();

            if ($assignee === null) {
                return null;
            }

            $this->syncTicketAssignees->handle($actor, $ticket, ['user_ids' => [$assignee->getKey()]]);
            $desk->users()->updateExistingPivot($assignee->getKey(), ['last_assigned_at' => now()]);

            return $assignee;
        });
    }
}
