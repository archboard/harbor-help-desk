<?php

namespace App\Actions\Tickets;

use App\Models\Workspace;
use Illuminate\Support\Facades\RateLimiter;

class NewTicketRateLimit
{
    /**
     * Count a new ticket from the address against the workspace's hourly limit, shared by email and the
     * portal. True when the limit is already reached, in which case nothing is counted.
     */
    public function isExceeded(Workspace $workspace, string $email): bool
    {
        $key = "inbound-email:{$workspace->getKey()}:".mb_strtolower($email);

        if (RateLimiter::tooManyAttempts($key, $workspace->settings->mailNewTicketsPerSenderPerHour)) {
            return true;
        }

        RateLimiter::hit($key, 3600);

        return false;
    }
}
