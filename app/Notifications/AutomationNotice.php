<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Automation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A message from an automation: a `notify` action's rendered template, or the notice that
 * an automation failed. Delivered synchronously, like {@see TicketActivity}. Recipients follow their
 * preferences for {@see NotificationType::AUTOMATION}.
 */
class AutomationNotice extends Notification
{
    public function __construct(
        public Automation $automation,
        public ?Ticket $ticket,
        public string $title,
        public string $body,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return [
            ...$notifiable->notificationChannels($this->automation->workspace, NotificationType::AUTOMATION),
            'broadcast',
        ];
    }

    public function databaseType(User $notifiable): string
    {
        return NotificationType::AUTOMATION->value;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $workspace = $this->automation->workspace;
        $url = $workspace->url($this->ticket === null ? '/' : "/tickets/{$this->ticket->id}");

        return (new MailMessage)
            ->subject($this->title)
            ->line($this->title)
            ->action(__('Open ticket'), $url)
            ->view(['mail.ticket-activity', 'mail.ticket-activity-text'], [
                'description' => $this->title,
                'body' => $this->body,
                'url' => $url,
                'ticket' => $this->ticket,
                'workspaceName' => $workspace->name,
            ]);
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
        return [
            'type' => NotificationType::AUTOMATION->value,
            'automation_id' => $this->automation->getKey(),
            'actor' => $this->automation->name,
            'title' => $this->title,
            'description' => $this->body,
            ...($this->ticket === null ? [] : [
                'ticket_id' => $this->ticket->id,
                'ticket_number' => $this->ticket->number,
                'subject' => $this->ticket->subject,
            ]),
        ];
    }
}
