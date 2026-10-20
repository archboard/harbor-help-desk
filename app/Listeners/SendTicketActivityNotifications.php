<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\TicketEventType;
use App\Events\TicketActivityRecorded;
use App\Models\Contact;
use App\Models\DatabaseNotification;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Notifications\TicketActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

        $sends = match (true) {
            $ticketEvent->type === TicketEventType::CREATED => [[
                NotificationType::NEW_TICKET,
                $ticket->assignees()->doesntExist() ? $this->deskMembers($ticket) : collect(),
            ]],
            $ticketEvent->type === TicketEventType::ASSIGNEE_ADDED => [[
                NotificationType::ASSIGNED,
                User::query()->whereKey($ticketEvent->properties['user_id'])->get(),
            ]],
            $ticketEvent->type === TicketEventType::COMMENT_ADDED && $ticketEvent->actor instanceof Contact => [[
                NotificationType::REQUESTER_REPLIED,
                $ticket->assignees()->get()->whenEmpty(fn () => $this->deskMembers($ticket)),
            ]],
            $ticketEvent->type === TicketEventType::INTERNAL_NOTE_ADDED => $this->noteSends($ticketEvent, $ticket),
            $this->isTicketUpdate($ticketEvent) => [[NotificationType::TICKET_UPDATED, $ticket->assignees()->get()]],
            default => [],
        };

        foreach ($sends as [$type, $candidates]) {
            $recipients = $this->allowed($candidates, $ticketEvent, $ticket);

            if ($type === NotificationType::TICKET_UPDATED) {
                $this->sendCollapsed($recipients, $ticketEvent, $ticket);
            } else {
                Notification::sendNow($recipients, new TicketActivity($ticketEvent, $type));
            }
        }
    }

    /**
     * Mentioned users get `mentioned`; the other assignees get the usual update.
     *
     * @return list<array{NotificationType, Collection<int, User>}>
     */
    protected function noteSends(TicketEvent $ticketEvent, Ticket $ticket): array
    {
        $mentionedIds = $ticketEvent->properties['mentioned_user_ids'] ?? [];

        return [
            [NotificationType::MENTIONED, User::query()->whereKey($mentionedIds)->get()],
            [NotificationType::TICKET_UPDATED, $ticket->assignees()->get()->reject(fn (User $user): bool => in_array($user->id, $mentionedIds, true))],
        ];
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    protected function sendCollapsed(Collection $recipients, TicketEvent $ticketEvent, Ticket $ticket): void
    {
        $type = NotificationType::TICKET_UPDATED;

        // ponytail: skip recipients with an unread update for this ticket. The ceiling is that an
        // agent who never reads the first one misses later changes; the fix is a delayed, unique job per ticket.
        // The lock makes the check and the send atomic across the listener jobs of a bulk update.
        Cache::lock("ticket-updated-notifications:{$ticket->id}", 10)->block(10, function () use ($recipients, $ticketEvent, $ticket, $type): void {
            $alreadyNotified = DatabaseNotification::query()
                ->whereNull('read_at')
                ->where('type', $type->value)
                ->where('data->ticket_id', $ticket->id)
                ->whereIn('notifiable_id', $recipients->pluck('id'))
                ->pluck('notifiable_id');

            Notification::sendNow(
                $recipients->reject(fn (User $user): bool => $alreadyNotified->contains($user->id)),
                new TicketActivity($ticketEvent, $type),
            );
        });
    }

    protected function isTicketUpdate(TicketEvent $ticketEvent): bool
    {
        return match ($ticketEvent->type) {
            TicketEventType::SUBJECT_CHANGED,
            TicketEventType::STATUS_CHANGED,
            TicketEventType::PRIORITY_CHANGED,
            TicketEventType::DUE_DATE_CHANGED,
            TicketEventType::DESK_CHANGED,
            TicketEventType::CUSTOM_FIELD_CHANGED,
            TicketEventType::ASSIGNEE_REMOVED,
            TicketEventType::TAG_ADDED,
            TicketEventType::TAG_REMOVED,
            TicketEventType::ATTACHMENT_ADDED,
            TicketEventType::ATTACHMENT_REMOVED => true,
            TicketEventType::COMMENT_ADDED => $ticketEvent->actor instanceof User,
            default => false,
        };
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
