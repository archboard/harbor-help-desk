<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\BusinessSchedule;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\TicketMetric;
use App\Models\User;
use Carbon\CarbonInterface;

class RecomputeTicketMetrics
{
    /**
     * Rebuild the ticket's metrics and its replies' response times from scratch, so running it again
     * (after an edit, a deleted comment, a reopen, or as a backfill) always lands on the same numbers.
     *
     * A reply's response time runs from the earliest customer comment since the previous public
     * reply. The first such reply is the first response, so a ticket an agent opened with no customer
     * message has none. Resolution runs from creation to when the ticket last became resolved, and
     * a reopen clears it.
     */
    public function handle(Ticket $ticket): void
    {
        $ticket->loadMissing(['slaPolicy.businessSchedule', 'desk.slaPolicy.businessSchedule']);
        $schedule = $ticket->effectiveSlaPolicy()?->businessSchedule;

        $metric = [
            'first_responded_at' => null,
            'first_responder_id' => null,
            'first_response_minutes' => null,
            'first_response_business_minutes' => null,
            'resolved_at' => null,
            'resolution_minutes' => null,
            'resolution_business_minutes' => null,
            'reopen_count' => 0,
        ];

        $comments = $ticket->comments()->withTrashed()->where('is_internal', false)->oldest()->orderBy('id')->get();
        $waitingSince = null;

        // Deleted comments neither start nor answer a wait, and their response times are cleared.
        foreach ($comments as $comment) {
            $response = [null, null];

            if (! $comment->trashed() && $comment->author_type === (new Contact)->getMorphClass()) {
                $waitingSince ??= $comment->created_at;
            } elseif (! $comment->trashed() && $comment->author_type === (new User)->getMorphClass() && $waitingSince !== null) {
                $response = $this->minutes($schedule, $waitingSince, $comment->created_at);
                $waitingSince = null;

                if ($metric['first_responded_at'] === null) {
                    $metric['first_responded_at'] = $comment->created_at;
                    $metric['first_responder_id'] = $comment->author_id;
                    [$metric['first_response_minutes'], $metric['first_response_business_minutes']] = $response;
                }
            }

            $comment->fill(['response_minutes' => $response[0], 'response_business_minutes' => $response[1]]);
        }

        // Saved after the loop: withoutTimestamps() also stops casting created_at.
        TicketComment::withoutTimestamps(fn () => $comments->filter->isDirty()->each->save());

        $statusChanges = $ticket->events()->where('type', TicketEventType::STATUS_CHANGED)->get();
        $initialStatus = TicketStatus::tryFrom($statusChanges->first()?->properties['from'] ?? '') ?? $ticket->status;
        $resolvedAt = $initialStatus->isResolved() ? $ticket->created_at : null;

        // Solved → closed keeps the time it was first resolved.
        $statusChanges->each(function (TicketEvent $event) use (&$resolvedAt, &$metric): void {
            $wasResolved = TicketStatus::tryFrom($event->properties['from'] ?? '')?->isResolved() ?? false;
            $isResolved = TicketStatus::from($event->properties['to'])->isResolved();

            if ($isResolved && ! $wasResolved) {
                $resolvedAt = $event->created_at;
            } elseif (! $isResolved && $wasResolved) {
                $resolvedAt = null;
                $metric['reopen_count']++;
            }
        });

        if ($resolvedAt !== null) {
            $metric['resolved_at'] = $resolvedAt;
            [$metric['resolution_minutes'], $metric['resolution_business_minutes']] = $this->minutes($schedule, $ticket->created_at, $resolvedAt);
        }

        TicketMetric::updateOrCreate(['ticket_id' => $ticket->getKey()], $metric);
    }

    /**
     * @return array{int, int} Calendar and business minutes.
     */
    protected function minutes(?BusinessSchedule $schedule, CarbonInterface $from, CarbonInterface $to): array
    {
        return [BusinessSchedule::minutesBetweenFor(null, $from, $to), BusinessSchedule::minutesBetweenFor($schedule, $from, $to)];
    }
}
