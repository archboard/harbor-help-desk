<?php

namespace App\Actions\Mailboxes;

use App\Data\Mailboxes\CreateMailboxData;
use App\Enums\AuditAction;
use App\Enums\MailboxDriver;
use App\Models\AuditEvent;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateMailbox
{
    /**
     * Connect one of the workspace's own mailboxes: IMAP/SMTP with a password, Postmark with a
     * server token, or Google or Microsoft with OAuth tokens. The hosted mailbox is only made by {@see CreateHostedMailbox}.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): Mailbox
    {
        Gate::forUser($actor)->authorize('create', Mailbox::class);

        $data = CreateMailboxData::validateAndCreate($input);

        $mailbox = Mailbox::create([
            ...$data->filled(),
            // The token in a Postmark mailbox's inbound webhook URL.
            'push_secret' => $data->driver === MailboxDriver::POSTMARK ? Str::random(40) : null,
        ]);

        AuditEvent::record(AuditAction::MAILBOX_CREATED, $actor, $mailbox);

        return $mailbox;
    }
}
