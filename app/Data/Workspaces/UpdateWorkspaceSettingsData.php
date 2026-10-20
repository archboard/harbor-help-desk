<?php

namespace App\Data\Workspaces;

use App\Data\InputData;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Optional;

/**
 * Every property is optional, so each settings page submits only its own keys. `null` resets a
 * setting to its default. Rules that need the workspace live in `UpdateWorkspaceSettings`.
 */
class UpdateWorkspaceSettingsData extends InputData
{
    public function __construct(
        public string|Optional|null $mailNotificationMailboxId,
        #[Between(1, 1000)]
        public int|Optional|null $mailNewTicketsPerSenderPerHour,
    ) {}
}
