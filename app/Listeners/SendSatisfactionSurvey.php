<?php

namespace App\Listeners;

use App\Enums\SatisfactionScore;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Events\TicketActivityRecorded;
use App\Models\Workspace;
use App\Notifications\SatisfactionSurveyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\URL;

/**
 * Email the requester a Good/Bad survey the first time their ticket is solved. Queued, and tenant-aware
 * by the multitenancy default.
 */
class SendSatisfactionSurvey implements ShouldQueue
{
    public function handle(TicketActivityRecorded $event): void
    {
        $ticketEvent = $event->ticketEvent;
        $ticket = $ticketEvent->ticket;

        if ($ticketEvent->type !== TicketEventType::STATUS_CHANGED
            || $ticketEvent->properties['to'] !== TicketStatus::SOLVED->value
            || $ticket === null
            || ! Workspace::current()->settings->surveysEnabled
            || $ticket->survey_sent_at !== null
            || $ticket->satisfactionRating()->exists()
            || $ticket->requester === null) {
            return;
        }

        // Relative so the queued listener needs no request host: the link goes on the workspace's own.
        $links = collect(SatisfactionScore::cases())->mapWithKeys(fn (SatisfactionScore $score): array => [
            $score->value => Workspace::current()->url(URL::temporarySignedRoute(
                'portal.ratings.show',
                now()->addDays(30),
                ['ticket' => $ticket->getKey(), 'rating' => $score->value],
                absolute: false,
            )),
        ]);

        $ticket->requester->notify(new SatisfactionSurveyNotification($ticket, $links['good'], $links['bad']));
        $ticket->forceFill(['survey_sent_at' => now()])->save();
    }
}
