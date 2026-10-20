<?php

namespace App\Data\Mailboxes;

use App\Actions\Mailboxes\MailboxRules;
use App\Data\InputData;
use Spatie\LaravelData\Optional;

/**
 * Which of the address and settings may change depends on the mailbox's driver, so
 * `UpdateMailbox` checks those. Settings left out keep their stored values, passwords included.
 */
class UpdateMailboxData extends InputData
{
    /**
     * @param  list<array{address: string, desk_id: string}>|Optional  $routing
     * @param  array<string, mixed>|Optional  $settings
     */
    public function __construct(
        public string|Optional $name,
        public string|Optional $address,
        public string|Optional $defaultDeskId,
        public array|Optional $routing,
        public string|Optional|null $signature,
        public array|Optional $settings,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return MailboxRules::common(creating: false);
    }
}
