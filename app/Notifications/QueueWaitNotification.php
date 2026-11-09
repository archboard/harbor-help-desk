<?php

namespace App\Notifications;

use Laravel\Horizon\Notifications\LongWaitDetected;

/**
 * Horizon's long-wait alert, always by mail. Horizon's own decides its channels from a fixed
 * address, but the operators are looked up when the alert is sent.
 */
class QueueWaitNotification extends LongWaitDetected
{
    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }
}
