<?php

namespace App\Console\Commands;

use App\Actions\Tickets\SendDueTicketReminders;
use App\Models\TicketReminder;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:send-reminders')]
#[Description('Send every workspace\'s due ticket reminders')]
class SendTicketReminders extends Command
{
    public function handle(SendDueTicketReminders $sendDueTicketReminders): void
    {
        $dueWorkspaceIds = TicketReminder::withoutGlobalScope('workspace')
            ->select('workspace_id')
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now());

        Workspace::query()->whereIn('id', $dueWorkspaceIds)->get()->eachCurrent(function () use ($sendDueTicketReminders): void {
            // ponytail: one batch per workspace per run; loop until it returns 0 if a backlog builds up.
            $sendDueTicketReminders->handle();
        });
    }
}
