<?php

namespace App\Notifications;

use App\Models\TicketReminder;
use App\Models\User;
use Illuminate\Notifications\Notification;

class TicketReminderDue extends Notification
{
    public function __construct(public TicketReminder $reminder) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $ticket = $this->reminder->ticket;

        return [
            'workspace_id' => $ticket->workspace_id,
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->number,
            'subject' => $ticket->subject,
            'reminder_id' => $this->reminder->id,
            'note' => $this->reminder->note,
        ];
    }
}
