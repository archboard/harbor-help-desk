<?php

namespace App\Data\Mailboxes;

use App\Actions\Mailboxes\CreateHostedMailbox;
use App\Actions\Mailboxes\MailboxRules;
use App\Data\InputData;
use App\Enums\MailboxDriver;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * One of the workspace's own mailboxes: IMAP/SMTP with passwords, or Google or Microsoft with
 * OAuth tokens. The settings hold secrets, so they're accepted here and never sent back. The
 * hosted mailbox is only made by {@see CreateHostedMailbox}.
 */
class CreateMailboxData extends InputData
{
    /**
     * @param  list<array{address: string, desk_id: string}>|Optional  $routing
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public MailboxDriver $driver,
        public string $name,
        public string $address,
        public string $defaultDeskId,
        public array|Optional $routing,
        public string|Optional|null $signature,
        public array $settings,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            ...MailboxRules::common(creating: true),
            ...MailboxRules::connection(MailboxDriver::tryFrom((string) ($context->payload['driver'] ?? '')), creating: true),
        ];
    }
}
