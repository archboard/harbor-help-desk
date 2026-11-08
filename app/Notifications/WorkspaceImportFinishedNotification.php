<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceImportFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public bool $succeeded, public ?string $error = null) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        if ($this->succeeded) {
            return (new MailMessage)
                ->subject(__('Your workspace import is done'))
                ->line(__('The data was imported. Mailboxes were imported disconnected: reconnect them, and generate new secret URLs for webhook automations.'));
        }

        return (new MailMessage)
            ->subject(__('Your workspace import failed'))
            ->line(__('Nothing was imported. The workspace is unchanged.'))
            ->line((string) $this->error);
    }
}
