<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SyncTicketAssignees
{
    /**
     * Make `user_ids` the ticket's assignees, recording who was assigned and unassigned.
     * Assignees must be on the ticket's desk.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Ticket $ticket, array $input): void
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        $userIds = Validator::make($input, [
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['string', 'distinct'],
        ])->validate()['user_ids'];

        $members = $ticket->desk->users()->whereKey($userIds)->get();

        if ($members->count() !== count($userIds)) {
            throw ValidationException::withMessages([
                'user_ids' => __('Only members of the ticket’s desk can be assigned.'),
            ]);
        }

        DB::transaction(function () use ($actor, $ticket, $members): void {
            $current = $ticket->assignees()->get();

            foreach ($members->diff($current) as $user) {
                $ticket->assignees()->attach($user, ['workspace_id' => $ticket->workspace_id]);
                $ticket->recordEvent(TicketEventType::ASSIGNEE_ADDED, $actor, ['user_id' => $user->id, 'name' => $user->name]);
            }

            foreach ($current->diff($members) as $user) {
                $ticket->assignees()->detach($user);
                $ticket->recordEvent(TicketEventType::ASSIGNEE_REMOVED, $actor, ['user_id' => $user->id, 'name' => $user->name]);
            }
        });

        $ticket->unsetRelation('assignees');
    }
}
