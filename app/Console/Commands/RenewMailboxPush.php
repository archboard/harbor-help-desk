<?php

namespace App\Console\Commands;

use App\Actions\Mailboxes\SubscribeMailboxPush;
use App\Enums\MailboxDriver;
use App\Models\Mailbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mailboxes:renew-push')]
#[Description('Renew Gmail and Microsoft Graph push subscriptions expiring within 36 hours')]
class RenewMailboxPush extends Command
{
    /**
     * Landlord code: one query across workspaces, then each renewal runs inside its mailbox's
     * workspace. Gmail watches last at most 7 days, Graph subscriptions under 3.
     */
    public function handle(SubscribeMailboxPush $subscribeMailboxPush): void
    {
        Mailbox::withoutGlobalScope('workspace')
            ->whereIn('driver', [MailboxDriver::GOOGLE, MailboxDriver::MICROSOFT])
            ->whereNotNull('push_expires_at')
            ->where('push_expires_at', '<=', now()->addHours(36))
            ->with('workspace')
            ->lazyById()
            ->each(fn (Mailbox $mailbox) => $mailbox->workspace->execute(function () use ($mailbox, $subscribeMailboxPush): void {
                $subscribeMailboxPush->handle($mailbox);
            }));
    }
}
