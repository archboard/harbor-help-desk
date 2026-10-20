<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\TicketEventType;
use App\Events\TicketActivityRecorded;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Notifications\TicketActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Turn recorded ticket activity into agent notifications. Queued, and tenant-aware by the
 * multitenancy default, so the event's models are re-fetched inside its workspace.
 */
class SendTicketActivityNotifications implements ShouldQueue
{
    public function handle(TicketActivityRecorded $event): void
    {
        $ticketEvent = $event->ticketEvent;
        $ticket = $ticketEvent->ticket;

        // Deleted before the queued listener ran.
        if ($ticket === null) {
            return;
        }

        [$type, $candidates] = match (true) {
            $ticketEvent->type === TicketEventType::CREATED => [
                NotificationType::NEW_TICKET,
                $ticket->assignees()->doesntExist() ? $this->deskMembers($ticket) : collect(),
            ],
            $ticketEvent->type === TicketEventType::ASSIGNEE_ADDED => [
                NotificationType::ASSIGNED,
                User::query()->whereKey($ticketEvent->properties['user_id'])->get(),
            ],
            $ticketEvent->type === TicketEventType::COMMENT_ADDED && $ticketEvent->actor instanceof Contact => [
                NotificationType::REQUESTER_REPLIED,
                $ticket->assignees()->get()->whenEmpty(fn () => $this->deskMembers($ticket)),
            ],
            default => [null, collect()],
        };

        if ($type === null) {
            return;
        }

        Notification::send($this->allowed($candidates, $ticketEvent, $ticket), new TicketActivity($ticketEvent, $type));
    }

    /**
     * @return Collection<int, User>
     */
    protected function deskMembers(Ticket $ticket): Collection
    {
        return $ticket->desk->users;
    }

    /**
     * Drop the actor, people who left the workspace and people who can't view the ticket.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    protected function allowed(Collection $users, TicketEvent $ticketEvent, Ticket $ticket): Collection
    {
        $memberIds = $ticket->workspace->users()->whereKey($users->pluck('id'))->pluck('users.id');

        return $users
            ->filter(fn (User $user): bool => $memberIds->contains($user->id)
                && ! $ticketEvent->actor?->is($user)
                && Gate::forUser($user)->allows('view', $ticket))
            ->values();
    }
}
