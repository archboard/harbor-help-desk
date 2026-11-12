<?php

namespace App\Data\Workspaces;

use App\Data\InputData;
use App\Enums\PortalAccess;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Optional;

/**
 * Every property is optional, so each settings page submits only its own keys. `null` resets a
 * setting to its default. Rules that need the workspace live in `UpdateWorkspaceSettings`.
 */
class UpdateWorkspaceSettingsData extends InputData
{
    /**
     * @param  list<string>|Optional|null  $attachmentsBlockedExtensions
     * @param  list<string>|Optional|null  $onboardingSkippedSteps
     */
    public function __construct(
        public string|Optional|null $mailNotificationMailboxId,
        #[Uuid]
        public string|Optional|null $microsoftClientId,
        #[Regex('/^[A-Za-z0-9.-]+$/'), Max(255)]
        public string|Optional|null $microsoftTenant,
        /** Write-only: saved encrypted outside the settings and never returned. */
        #[Max(255)]
        public string|Optional|null $microsoftClientSecret,
        #[Between(1, 1000)]
        public int|Optional|null $mailNewTicketsPerSenderPerHour,
        #[Min(1)]
        public int|Optional|null $attachmentsMaxSizeKb,
        public array|Optional|null $attachmentsBlockedExtensions,
        public bool|Optional|null $automationsAllowPrivateUrls,
        public bool|Optional|null $portalEnabled,
        public PortalAccess|Optional|null $portalAccess,
        public bool|Optional|null $surveysEnabled,
        #[Between(1, 3650)]
        public int|Optional|null $retentionResolvedTicketDays,
        #[Between(1, 365)]
        public int|Optional|null $retentionTrashDays,
        public bool|Optional|null $onboardingDismissed,
        public array|Optional|null $onboardingSkippedSteps,
    ) {}
}
