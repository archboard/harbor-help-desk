<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketActivity extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

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

        return (new MailMessage)
            ->subject("#{$ticket->number} {$ticket->subject}")
            ->line($this->ticketEvent->description())
            ->action(__('Open ticket'), $ticket->workspace->url("/tickets/{$ticket->id}"));
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
            'description' => $this->ticketEvent->description(),
        ];
    }
}
