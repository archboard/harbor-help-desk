<?php

namespace App\Data\Workspaces;

use App\Casts\WorkspaceSettingsCast;
use Spatie\LaravelData\Data;

/**
 * The workspace's settings with their defaults. Only values that differ from the defaults are
 * stored; see {@see WorkspaceSettingsCast}. A new setting is a property here and on
 * {@see UpdateWorkspaceSettingsData}.
 */
class WorkspaceSettings extends Data
{
    public function __construct(
        /** The mailbox app email (notifications, sign-in links) is sent from; null means the oldest mailbox. */
        public ?string $mailNotificationMailboxId = null,
        /** How many new tickets one sender can open per hour by email; replies don't count. */
        public int $mailNewTicketsPerSenderPerHour = 20,
    ) {}
}
