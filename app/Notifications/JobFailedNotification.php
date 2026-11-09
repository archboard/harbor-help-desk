<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells the operators that a queued job failed. Not queued, so a broken queue can still
 * report itself, and sent to an on-demand mail route rather than to a user.
 */
class JobFailedNotification extends Notification
{
    public function __construct(
        public string $jobName,
        public string $connection,
        public string $queue,
        public string $exceptionMessage,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Job failed: :job', ['job' => $this->jobName]))
            ->line(__(':job failed on the :queue queue (:connection).', ['job' => $this->jobName, 'queue' => $this->queue, 'connection' => $this->connection]))
            ->line(Str::limit($this->exceptionMessage, 500))
            ->line(__('Alerts for this job are sent at most once an hour. See the failed jobs in Horizon.'));
    }
}
