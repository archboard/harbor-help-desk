<?php

namespace App\Data\Mailboxes;

use Spatie\LaravelData\Data;

/**
 * Mail to this address (To or Cc) opens tickets on the desk.
 */
class MailboxRoutingRuleData extends Data
{
    public function __construct(
        public string $address,
        public string $deskId,
    ) {}
}
