<?php

namespace App\Data\Mailboxes;

use App\Enums\MailboxDriver;
use App\Models\Mailbox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * Never exposes passwords or OAuth tokens: only the servers and usernames of an IMAP/SMTP
 * mailbox's settings.
 */
class MailboxData extends Data
{
    /**
     * @param  string|null  $inboundAddress  Hosted only: the address tenants forward their own mail to.
     * @param  Collection<int, MailboxRoutingRuleData>  $routing
     * @param  array{imap: MailboxServerData, smtp: MailboxServerData}|Lazy  $settings  IMAP only.
     */
    public function __construct(
        public string $id,
        public MailboxDriver $driver,
        public string $driverLabel,
        public string $name,
        public string $address,
        public ?string $inboundAddress,
        public string $defaultDeskId,
        public Collection $routing,
        public ?string $signature,
        public array|Lazy $settings,
        #[LiteralTypeScriptType("'webhook' | 'push' | 'watch' | 'poll'")]
        public string $receivesBy,
        public ?CarbonImmutable $polledAt,
        public ?string $lastError,
        public ?CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(Mailbox $mailbox): self
    {
        return new self(
            id: $mailbox->id,
            driver: $mailbox->driver,
            driverLabel: $mailbox->driver->label(),
            name: $mailbox->name,
            address: $mailbox->address,
            inboundAddress: $mailbox->inbound_address,
            defaultDeskId: $mailbox->default_desk_id,
            routing: MailboxRoutingRuleData::collect(collect($mailbox->routing)),
            signature: $mailbox->signature,
            settings: Lazy::when(fn (): bool => $mailbox->driver === MailboxDriver::IMAP, fn (): array => [
                'imap' => MailboxServerData::from($mailbox->settings['imap']),
                'smtp' => MailboxServerData::from($mailbox->settings['smtp']),
            ]),
            receivesBy: match (true) {
                $mailbox->driver === MailboxDriver::HOSTED => 'webhook',
                (bool) $mailbox->push_expires_at?->isFuture() => 'push',
                (bool) config('harbor.mail.watch') => 'watch',
                default => 'poll',
            },
            polledAt: $mailbox->polled_at,
            lastError: $mailbox->last_error,
            createdAt: $mailbox->created_at,
        );
    }
}
