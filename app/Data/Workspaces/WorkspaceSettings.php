<?php

namespace App\Data\Workspaces;

use App\Casts\WorkspaceSettingsCast;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

/**
 * The workspace's settings with their defaults. Only values that differ from the defaults are
 * stored; see {@see WorkspaceSettingsCast}. A new setting is a property here and on
 * {@see UpdateWorkspaceSettingsData}.
 */
class WorkspaceSettings extends Data
{
    /**
     * @param  list<string>  $attachmentsBlockedExtensions
     */
    public function __construct(
        /** The mailbox app email (notifications, sign-in links) is sent from; null means the oldest mailbox. */
        public ?string $mailNotificationMailboxId = null,
        /** How many new tickets one sender can open per hour by email; replies don't count. */
        public int $mailNewTicketsPerSenderPerHour = 20,
        /** The largest file that can be attached, in kilobytes; the platform cap and the server's PHP limits still apply. */
        public int $attachmentsMaxSizeKb = 25600,
        /** File extensions that can't be attached, lowercase and without dots; the cloud blocks the platform list as well. */
        public array $attachmentsBlockedExtensions = ['bat', 'cmd', 'com', 'cpl', 'dll', 'exe', 'hta', 'jar', 'js', 'jse', 'lnk', 'msi', 'msp', 'ps1', 'scr', 'sh', 'vbe', 'vbs', 'wsf'],
        /** Lets automation webhooks reach private, loopback and link-local addresses (LAN targets). Self-hosted only; always off in the cloud. */
        public bool $automationsAllowPrivateUrls = false,
    ) {}

    /**
     * The largest attachment in kilobytes: the setting, held down by the platform cap and PHP's
     * `upload_max_filesize` and `post_max_size`.
     */
    public function effectiveAttachmentMaxKb(): int
    {
        return min(
            $this->attachmentsMaxSizeKb,
            (int) config('harbor.attachments.max_size_kb'),
            (int) floor(UploadedFile::getMaxFilesize() / 1024),
        );
    }

    /**
     * Whether automation webhooks may reach private addresses: never in multitenant mode, whatever is stored.
     */
    public function effectiveAllowPrivateUrls(): bool
    {
        return ! config('harbor.multitenant') && $this->automationsAllowPrivateUrls;
    }

    /**
     * @return list<string>
     */
    public function effectiveBlockedExtensions(): array
    {
        return config('harbor.multitenant')
            ? array_values(array_unique([...$this->attachmentsBlockedExtensions, ...config('harbor.attachments.blocked_extensions')]))
            : $this->attachmentsBlockedExtensions;
    }
}
