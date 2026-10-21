<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Delivered synchronously: the listener that sends it is already queued, and its database row
 * must exist before the next listener checks for unread repeats.
 */
class TicketActivity extends Notification
{
    public function __construct(public TicketEvent $ticketEvent, public NotificationType $type) {}

    /**
     * The channels the user chose for this type, plus a broadcast ping that tells their open
     * windows to reload the inbox.
     *
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return [
            ...$notifiable->notificationChannels($this->ticketEvent->ticket->workspace, $this->type),
            'broadcast',
        ];
    }

    public function databaseType(User $notifiable): string
    {
        return $this->type->value;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $ticket = $this->ticketEvent->ticket;

        $description = $this->ticketEvent->description($notifiable->timezone);
        $url = $ticket->workspace->url("/tickets/{$ticket->id}");

        // A plain view, not markdown lines: comment bodies are plain text, and CommonMark would
        // reformat them and turn a requester's `[text](url)` into a live link.
        return (new MailMessage)
            ->subject("#{$ticket->number} {$ticket->subject}")
            ->line($description)
            ->action(__('Open ticket'), $url)
            ->view(['mail.ticket-activity', 'mail.ticket-activity-text'], [
                'description' => $description,
                'body' => $this->commentBody(),
                'url' => $url,
                'ticket' => $ticket,
                'workspaceName' => $ticket->workspace->name,
            ]);
    }

    /**
     * The comment's text for reply and note events, or null when there's none or it was deleted.
     */
    protected function commentBody(): ?string
    {
        $commentId = $this->ticketEvent->properties['comment_id'] ?? null;

        return $commentId === null ? null : TicketComment::query()->whereKey($commentId)->first()?->body;
    }

    /**
     * Broadcasts carry no data. Clients reload through the normal endpoints.
     */
    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $ticket = $this->ticketEvent->ticket;

        return [
            'type' => $this->type->value,
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->number,
            'subject' => $ticket->subject,
            'actor' => $this->ticketEvent->actorName(),
            'description' => $this->ticketEvent->description($notifiable->timezone),
        ];
    }
}
