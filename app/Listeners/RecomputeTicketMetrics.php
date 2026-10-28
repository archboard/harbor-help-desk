<?php

namespace App\Listeners;

use App\Actions\Tickets\RecomputeTicketMetrics as RecomputeTicketMetricsAction;
use App\Enums\TicketEventType;
use App\Events\TicketActivityRecorded;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Keep a ticket's reporting metrics current. Queued, and tenant-aware by the multitenancy default.
 * Order doesn't matter, since the Action recomputes from scratch.
 */
class RecomputeTicketMetrics implements ShouldQueue
{
    public function __construct(protected RecomputeTicketMetricsAction $recomputeTicketMetrics) {}

    public function handle(TicketActivityRecorded $event): void
    {
        $ticket = $event->ticketEvent->ticket;

        if ($ticket !== null && in_array($event->ticketEvent->type, [
            TicketEventType::CREATED,
            TicketEventType::COMMENT_ADDED,
            TicketEventType::COMMENT_DELETED,
            TicketEventType::STATUS_CHANGED,
            TicketEventType::DESK_CHANGED,
            TicketEventType::SLA_POLICY_CHANGED,
        ], true)) {
            $this->recomputeTicketMetrics->handle($ticket);
        }
    }
}
