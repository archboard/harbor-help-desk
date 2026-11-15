<?php

namespace App\Data\Mailboxes;

use Spatie\LaravelData\Data;

/**
 * How one side of a mailbox test went, with what happened or the server's error.
 */
class MailboxCheckData extends Data
{
    public function __construct(
        public bool $passed,
        public string $message,
    ) {}
}
