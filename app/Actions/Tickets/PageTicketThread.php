<?php

namespace App\Actions\Tickets;

use App\Data\Tickets\PageTicketThreadData;
use App\Data\Tickets\TicketCommentData;
use App\Data\Tickets\TicketEventData;
use App\Data\Tickets\TicketThreadData;
use App\Enums\TicketEventType;
use App\Models\Ticket;
use Spatie\LaravelData\Optional;

class PageTicketThread
{
    public const int LIMIT = 50;

    /**
     * Events that the thread already shows as comments.
     */
    private const array EVENTS_SHOWN_AS_COMMENTS = [
        TicketEventType::COMMENT_ADDED,
        TicketEventType::INTERNAL_NOTE_ADDED,
    ];

    /**
     * A page of the ticket's thread: the newest comments, or the ones before the comment `before`. The
     * events come with it from the page's oldest comment up to the newer page's oldest comment, so
     * across pages none is dropped or shown twice. A `before` that isn't one of the ticket's comments
     * gives an empty page. The caller authorizes the ticket.
     *
     * @param  array<string, mixed>  $input
     * @param  bool  $publicOnly  Leave out internal notes and all events, for the customer portal.
     */
    public function handle(Ticket $ticket, array $input, bool $publicOnly = false, int $limit = self::LIMIT): TicketThreadData
    {
        $before = PageTicketThreadData::validateAndCreate($input)->before;
        $before = $before instanceof Optional ? null : $before;

        $newerBoundary = null;

        if ($before !== null) {
            $newerBoundary = $ticket->comments()->withTrashed()->whereKey($before)->value('created_at');

            if ($newerBoundary === null) {
                return new TicketThreadData([], [], null);
            }
        }

        $comments = $ticket->comments()
            ->reorder()
            ->when($publicOnly, fn ($query) => $query->where('is_internal', false))
            ->when($before !== null, fn ($query) => $query->where('id', '<', $before))
            ->with(['author', 'attachments'])
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasEarlier = $comments->count() > $limit;
        $comments = $comments->take($limit)->reverse()->values();
        $oldest = $comments->first();

        $events = $publicOnly ? collect() : $ticket->events()
            ->with('actor')
            ->whereNotIn('type', self::EVENTS_SHOWN_AS_COMMENTS)
            ->when($hasEarlier, fn ($query) => $query->where('created_at', '>=', $oldest->created_at))
            ->when($newerBoundary !== null, fn ($query) => $query->where('created_at', '<', $newerBoundary))
            ->get();

        return new TicketThreadData(
            comments: TicketCommentData::collect($comments)->all(),
            events: TicketEventData::collect($events)->all(),
            earlierBefore: $hasEarlier ? $oldest->id : null,
        );
    }
}
