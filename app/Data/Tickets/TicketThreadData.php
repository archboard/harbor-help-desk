<?php

namespace App\Data\Tickets;

use Spatie\LaravelData\Data;

/**
 * One page of a ticket's thread, oldest first.
 */
class TicketThreadData extends Data
{
    /**
     * @param  array<int, TicketCommentData>  $comments
     * @param  array<int, TicketEventData>  $events  The history from this page's oldest comment up to the newer page.
     * @param  string|null  $earlierBefore  The `before` cursor for the next older page, null on the oldest page.
     */
    public function __construct(
        public array $comments,
        public array $events,
        public ?string $earlierBefore,
    ) {}
}
