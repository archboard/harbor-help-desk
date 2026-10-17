<?php

namespace App\Actions\Tickets;

use App\Models\TicketReminder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CancelTicketReminder
{
    public function handle(User $actor, TicketReminder $reminder): void
    {
        Gate::forUser($actor)->authorize('delete', $reminder);

        $reminder->delete();
    }
}
