<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

/**
 * Whether report durations count every minute or only the ticket's business hours.
 */
enum ReportBasis: string
{
    use HasLabels;

    case CALENDAR = 'calendar';
    case BUSINESS = 'business';

    public function label(): string
    {
        return match ($this) {
            self::CALENDAR => __('Calendar hours'),
            self::BUSINESS => __('Business hours'),
        };
    }

    /**
     * @return literal-string
     */
    public function firstResponseColumn(): string
    {
        return match ($this) {
            self::CALENDAR => 'ticket_metrics.first_response_minutes',
            self::BUSINESS => 'ticket_metrics.first_response_business_minutes',
        };
    }

    /**
     * @return literal-string
     */
    public function replyColumn(): string
    {
        return match ($this) {
            self::CALENDAR => 'ticket_comments.response_minutes',
            self::BUSINESS => 'ticket_comments.response_business_minutes',
        };
    }

    /**
     * @return literal-string
     */
    public function resolutionColumn(): string
    {
        return match ($this) {
            self::CALENDAR => 'ticket_metrics.resolution_minutes',
            self::BUSINESS => 'ticket_metrics.resolution_business_minutes',
        };
    }
}
