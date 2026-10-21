<?php

namespace App\Actions\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ClaimNextTicket
{
    public function __construct(protected SyncTicketAssignees $syncTicketAssignees) {}

    /**
     * Assign the next waiting ticket to the user: open, unassigned and on a desk they belong to,
     * urgent first, then oldest. Away and rotation are ignored because the agent is asking for work.
     * Rows another claim holds are skipped, so two agents never get the same ticket.
     */
    public function handle(User $user): ?Ticket
    {
        return DB::transaction(function () use ($user): ?Ticket {
            $ticket = Ticket::query()
                ->visibleTo($user)
                ->whereHas('desk', fn (Builder $query) => $query
                    ->whereNull('desks.archived_at')
                    ->whereHas('users', fn (Builder $query) => $query->whereKey($user->getKey())))
                ->where('status', TicketStatus::OPEN)
                ->doesntHave('assignees')
                ->orderByRaw(
                    'case priority when ? then 0 when ? then 1 when ? then 2 else 3 end',
                    [TicketPriority::URGENT->value, TicketPriority::HIGH->value, TicketPriority::NORMAL->value],
                )
                ->oldest()
                ->orderBy('id')
                ->lock('for update skip locked')
                ->first();

            if ($ticket === null) {
                return null;
            }

            $this->syncTicketAssignees->handle($user, $ticket, ['user_ids' => [$user->getKey()]]);
            $ticket->desk->users()->updateExistingPivot($user->getKey(), ['last_assigned_at' => now()]);

            return $ticket;
        });
    }
}
