<?php

namespace App\Data\Mailboxes;

use Spatie\LaravelData\Data;

/**
 * The result of testing a mailbox. A side is null when the mailbox doesn't do it: a send-only
 * mailbox isn't checked for receiving, and the hosted one receives by webhook.
 */
class MailboxTestData extends Data
{
    public function __construct(
        public ?MailboxCheckData $sending,
        public ?MailboxCheckData $receiving,
    ) {}
}
