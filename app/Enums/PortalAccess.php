<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum PortalAccess: string
{
    use HasLabels;

    case ANYONE = 'anyone';
    case CONTACTS = 'contacts';

    public function label(): string
    {
        return match ($this) {
            self::ANYONE => __('Anyone with an email address'),
            self::CONTACTS => __('Existing contacts only'),
        };
    }
}
