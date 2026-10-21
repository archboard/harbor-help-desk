<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum TicketSource: string
{
    use HasLabels;

    case EMAIL = 'email';
    case WEB = 'web';
    case API = 'api';
    case WEBHOOK = 'webhook';
    case AUTOMATION = 'automation';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => __('Email'),
            self::WEB => __('Web'),
            self::API => __('API'),
            self::WEBHOOK => __('Webhook'),
            self::AUTOMATION => __('Automation'),
        };
    }
}
