<?php

namespace App\Actions\Mailboxes;

use App\Data\Mailboxes\UpdateMailboxData;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateMailbox
{
    /**
     * Update the mailbox. Changed IMAP settings restart polling from the newest message.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Mailbox $mailbox, array $input): Mailbox
    {
        Gate::forUser($actor)->authorize('update', $mailbox);

        $validated = UpdateMailboxData::validateAndCreateWith($input, MailboxRules::connection($mailbox->driver, creating: false))->filled();

        if (isset($validated['settings'])) {
            $validated['settings'] = array_replace_recursive($mailbox->settings ?? [], $validated['settings']);
            $validated['last_error'] = null;

            if ($validated['settings']['imap'] != ($mailbox->settings['imap'] ?? null)) {
                $validated['imap_uid_validity'] = null;
                $validated['imap_last_uid'] = null;
            }
        }

        $mailbox->update($validated);

        AuditEvent::record(AuditAction::MAILBOX_UPDATED, $actor, $mailbox, ['changed' => array_keys($validated)]);

        return $mailbox;
    }
}
