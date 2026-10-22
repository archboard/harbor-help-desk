<?php

namespace App\Automations;

use App\Enums\Concerns\HasLabels;

/**
 * Where a field is listed in the picker. Triggers say which groups they expose.
 */
enum FieldGroup: string
{
    use HasLabels;

    case TICKET = 'ticket';
    case REQUESTER = 'requester';
    case MESSAGE = 'message';
    case EVENT = 'event';
    case TIME = 'time';
    case SLA = 'sla';
    case CUSTOM_FIELDS = 'custom_fields';
    case WEBHOOK = 'webhook';

    public function label(): string
    {
        return match ($this) {
            self::TICKET => __('Ticket'),
            self::REQUESTER => __('Requester'),
            self::MESSAGE => __('Message'),
            self::EVENT => __('Event'),
            self::TIME => __('Time'),
            self::SLA => __('SLA'),
            self::CUSTOM_FIELDS => __('Custom fields'),
            self::WEBHOOK => __('Webhook'),
        };
    }
}
