<?php

namespace App\Actions\Mailboxes;

use App\Enums\AuditAction;
use App\Enums\MailboxDriver;
use App\Models\AuditEvent;
use App\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($actor, $mailbox): void {
            $workspace = Workspace::query()->findOrFail($mailbox->workspace_id);

            if ($workspace->settings->mailNotificationMailboxId === $mailbox->id) {
                $workspace->update(['settings' => [...$workspace->settings->toArray(), 'mail_notification_mailbox_id' => null]]);
            }

            $mailbox->delete();

            AuditEvent::record(AuditAction::MAILBOX_DELETED, $actor, $mailbox);
        });
    }
}
