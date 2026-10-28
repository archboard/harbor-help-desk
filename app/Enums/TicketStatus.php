<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum TicketStatus: string
{
    use HasLabels;

    case OPEN = 'open';
    case PENDING = 'pending';
    case ON_HOLD = 'on_hold';
    case SOLVED = 'solved';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => __('Open'),
            self::PENDING => __('Pending'),
            self::ON_HOLD => __('On hold'),
            self::SOLVED => __('Solved'),
            self::CLOSED => __('Closed'),
        };
    }

    /**
     * Whether the resolution clock stops while the ticket waits in this status.
     */
    public function pausesSla(): bool
    {
        return match ($this) {
            self::PENDING, self::ON_HOLD => true,
            self::OPEN, self::SOLVED, self::CLOSED => false,
        };
    }

    /**
     * Whether the ticket's work is done.
     */
    public function isResolved(): bool
    {
        return match ($this) {
            self::SOLVED, self::CLOSED => true,
            self::OPEN, self::PENDING, self::ON_HOLD => false,
        };
    }

    /**
     * The values of every resolved status, for queries.
     *
     * @return list<string>
     */
    public static function resolvedValues(): array
    {
        return array_values(array_map(fn (self $status): string => $status->value, array_filter(self::cases(), fn (self $status): bool => $status->isResolved())));
    }
}
