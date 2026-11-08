<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the creator of a webhook that it was switched off after too many failed deliveries.
 * Delivered synchronously, and follows the preferences for {@see NotificationType::AUTOMATION}.
 */
class WebhookDisabledNotification extends Notification
{
    public function __construct(public Webhook $webhook) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return [
            ...$notifiable->notificationChannels($this->webhook->workspace, NotificationType::AUTOMATION),
            'broadcast',
        ];
    }

    public function databaseType(User $notifiable): string
    {
        return NotificationType::AUTOMATION->value;
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->title())
            ->line($this->body())
            ->action(__('Open Harbor'), $this->webhook->workspace->url('/'));
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
            'webhook_id' => $this->webhook->getKey(),
            'actor' => 'Harbor',
            'title' => $this->title(),
            'description' => $this->body(),
        ];
    }

    protected function title(): string
    {
        return __('Webhook disabled');
    }

    protected function body(): string
    {
        return __('The webhook for :url was switched off after :count failed deliveries in a row.', [
            'url' => $this->webhook->url,
            'count' => Webhook::MAX_CONSECUTIVE_FAILURES,
        ]);
    }
}
