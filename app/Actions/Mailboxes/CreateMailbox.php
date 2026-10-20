<?php

namespace App\Actions\Mailboxes;

use App\Data\Mailboxes\CreateMailboxData;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CreateMailbox
{
    /**
     * Connect one of the workspace's own mailboxes: IMAP/SMTP with a password, or Google or
     * Microsoft with OAuth tokens. The hosted mailbox is only made by {@see CreateHostedMailbox}.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): Mailbox
    {
        Gate::forUser($actor)->authorize('create', Mailbox::class);

        return Mailbox::create(CreateMailboxData::validateAndCreate($input)->filled());
    }
}
