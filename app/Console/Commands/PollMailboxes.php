<?php

namespace App\Console\Commands;

use App\Jobs\PollMailbox;
use App\Models\Mailbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mailboxes:poll')]
#[Description('Queue a poll of every workspace\'s own mailboxes that is due for one')]
class PollMailboxes extends Command
{
    /**
     * Landlord code: one query across workspaces, then each job is dispatched with its
     * mailbox's workspace current so it runs tenant-aware.
     */
    public function handle(): void
    {
        Mailbox::withoutGlobalScope('workspace')
            ->needsScheduledPoll()
            ->with('workspace')
            ->lazyById()
            ->each(fn (Mailbox $mailbox) => $mailbox->workspace->execute(function () use ($mailbox): void {
                // A statement, not a returned PendingDispatch, so the job is queued while the workspace is current.
                PollMailbox::dispatch($mailbox);
            }));
    }
}
