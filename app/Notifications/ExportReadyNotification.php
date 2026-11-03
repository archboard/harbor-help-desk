<?php

namespace App\Notifications;

use App\Models\Export;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Export $export) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your data export is ready'))
            ->line(__('The export you requested is ready to download. The link works until :date.', ['date' => $this->export->expires_at->toFormattedDayDateString()]))
            ->action(__('Download export'), $this->export->signedDownloadUrl());
    }
}
