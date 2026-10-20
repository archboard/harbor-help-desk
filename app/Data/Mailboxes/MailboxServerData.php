<?php

namespace App\Data\Mailboxes;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * An IMAP or SMTP server, without its password.
 */
class MailboxServerData extends Data
{
    /**
     * @param  string|Optional  $folder  IMAP only.
     */
    public function __construct(
        public string $host,
        public int $port,
        #[LiteralTypeScriptType("'ssl' | 'tls' | 'starttls'")]
        public string $encryption,
        public string $username,
        public string|Optional $folder,
    ) {}
}
