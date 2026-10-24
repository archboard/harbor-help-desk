<?php

namespace App\Notifications;

use App\Models\Contact;
use App\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SatisfactionSurveyNotification extends Notification
{
    public function __construct(public Ticket $ticket, public string $goodUrl, public string $badUrl) {}

    /**
     * @return array<int, string>
     */
    public function via(Contact $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Contact $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('How did we do on #:number?', ['number' => $this->ticket->number]))
            ->line(__('Your request “:subject” was marked as solved. How would you rate the support you received?', ['subject' => $this->ticket->subject]))
            // One line, not two actions: a MailMessage keeps only its last action.
            ->line(__('**[Good](:good)** · **[Bad](:bad)**', ['good' => $this->goodUrl, 'bad' => $this->badUrl]))
            ->line(__('You can add a comment after choosing. These links expire in 30 days.'));
    }
}
