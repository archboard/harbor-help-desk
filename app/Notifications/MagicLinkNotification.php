<?php

namespace App\Notifications;

use App\Actions\Auth\SendMagicLink;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLinkNotification extends Notification
{
    public function __construct(public string $url) {}

    /**
     * @return array<int, string>
     */
    public function via(User|Contact $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User|Contact $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your sign-in link'))
            ->line(__('Click the button below to sign in.'))
            ->action(__('Sign in'), $this->url)
            ->line(__('This link expires in :minutes minutes and can only be used once.', [
                'minutes' => SendMagicLink::EXPIRES_IN_MINUTES,
            ]))
            ->line(__('If you did not request this link, you can ignore this email.'));
    }
}
