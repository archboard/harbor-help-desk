<?php

namespace App\Actions\Tickets;

use App\Models\TicketReminder;
use App\Models\User;
use App\Notifications\TicketReminderDue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class SendDueTicketReminders
{
    /**
     * Notify the current workspace's due reminders and mark them sent. Rows are claimed with
     * SKIP LOCKED, so overlapping runs never send a reminder twice. Reminders on deleted
     * tickets are marked sent without notifying anyone.
     *
     * @return int The number of reminders claimed.
     */
    public function handle(int $limit = 100): int
    {
        return DB::transaction(function () use ($limit): int {
            $reminders = TicketReminder::query()
                ->whereNull('sent_at')
                ->where('remind_at', '<=', now())
                ->orderBy('remind_at')
                ->limit($limit)
                ->lock('for update skip locked')
                ->with('ticket')
                ->get();

            foreach ($reminders as $reminder) {
                $reminder->update(['sent_at' => now()]);

                if ($reminder->ticket === null) {
                    continue;
                }

                Notification::send(
                    $reminder->recipients()->filter(fn (User $user): bool => Gate::forUser($user)->allows('view', $reminder->ticket)),
                    new TicketReminderDue($reminder),
                );
            }

            return $reminders->count();
        });
    }
}
