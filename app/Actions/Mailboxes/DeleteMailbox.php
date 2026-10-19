<?php

namespace App\Actions\Mailboxes;

use App\Enums\MailboxDriver;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteMailbox
{
    /**
     * Disconnect the mailbox. Its tickets stay, and their replies fall back to another mailbox.
     */
    public function handle(User $actor, Mailbox $mailbox): void
    {
        Gate::forUser($actor)->authorize('delete', $mailbox);

        if ($mailbox->driver === MailboxDriver::HOSTED) {
            throw ValidationException::withMessages([
                'mailbox' => __("The hosted mailbox can't be deleted."),
            ]);
        }

        $mailbox->delete();
    }
}
