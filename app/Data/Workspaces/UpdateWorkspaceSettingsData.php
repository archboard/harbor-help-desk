<?php

namespace App\Data\Workspaces;

use App\Data\InputData;
use App\Enums\PortalAccess;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Optional;

/**
 * Every property is optional, so each settings page submits only its own keys. `null` resets a
 * setting to its default. Rules that need the workspace live in `UpdateWorkspaceSettings`.
 */
class UpdateWorkspaceSettingsData extends InputData
{
    /**
     * @param  list<string>|Optional|null  $attachmentsBlockedExtensions
     */
    public function __construct(
        public string|Optional|null $mailNotificationMailboxId,
        #[Between(1, 1000)]
        public int|Optional|null $mailNewTicketsPerSenderPerHour,
        #[Min(1)]
        public int|Optional|null $attachmentsMaxSizeKb,
        public array|Optional|null $attachmentsBlockedExtensions,
        public bool|Optional|null $automationsAllowPrivateUrls,
        public bool|Optional|null $portalEnabled,
        public PortalAccess|Optional|null $portalAccess,
        public bool|Optional|null $surveysEnabled,
    ) {}
}
