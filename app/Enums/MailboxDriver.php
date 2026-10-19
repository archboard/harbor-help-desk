<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum MailboxDriver: string
{
    use HasLabels;

    case HOSTED = 'hosted';
    case IMAP = 'imap';
    case GOOGLE = 'google';
    case MICROSOFT = 'microsoft';

    public function label(): string
    {
        return match ($this) {
            self::HOSTED => __('Hosted'),
            self::IMAP => __('IMAP/SMTP'),
            self::GOOGLE => __('Google'),
            self::MICROSOFT => __('Microsoft 365'),
        };
    }
}
