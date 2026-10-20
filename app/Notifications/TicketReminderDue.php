<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\TicketReminder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReminderDue extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public TicketReminder $reminder) {}

    /**
     * The channels the user chose for reminders, plus a broadcast ping that tells their open
     * windows to reload the inbox.
     *
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return [
            ...$notifiable->notificationChannels($this->reminder->ticket->workspace, NotificationType::REMINDER_DUE),
            'broadcast',
        ];
    }

    public function databaseType(User $notifiable): string
    {
        return NotificationType::REMINDER_DUE->value;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $ticket = $this->reminder->ticket;

        return (new MailMessage)
            ->subject(__('Reminder: #:number :subject', ['number' => $ticket->number, 'subject' => $ticket->subject]))
            ->line($this->reminder->note ?? __('A reminder you set is due.'))
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
        $ticket = $this->reminder->ticket;

        return [
            'type' => NotificationType::REMINDER_DUE->value,
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->number,
            'subject' => $ticket->subject,
            'reminder_id' => $this->reminder->id,
            'note' => $this->reminder->note,
        ];
    }
}
