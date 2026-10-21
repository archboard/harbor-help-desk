<?php

namespace App\Actions\Tickets;

use App\Enums\SlaMetric;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\BusinessSchedule;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketSlaTarget;
use App\Models\User;
use Carbon\CarbonInterface;

class SyncTicketSlaTargets
{
    /**
     * Keep the ticket's SLA clocks in step with an event. Creation starts them from the desk's
     * policy; an agent's public reply meets first response; a status change pauses, resumes, meets
     * or reopens resolution; a priority or desk change retargets the unmet ones. The event's own
     * time is "now", because the listener runs after commit and may drift from when it happened.
     */
    public function handle(Ticket $ticket, TicketEvent $event): void
    {
        if (! in_array($event->type, [
            TicketEventType::CREATED,
            TicketEventType::COMMENT_ADDED,
            TicketEventType::STATUS_CHANGED,
            TicketEventType::PRIORITY_CHANGED,
            TicketEventType::DESK_CHANGED,
            TicketEventType::SLA_POLICY_CHANGED,
        ], true)) {
            return;
        }

        // Null when the ticket was deleted before the listener ran.
        $ticket = $ticket->fresh(['slaPolicy.businessSchedule', 'desk.slaPolicy.businessSchedule', 'slaTargets']);

        if ($ticket === null) {
            return;
        }

        match ($event->type) {
            TicketEventType::COMMENT_ADDED => $this->meetFirstResponse($ticket, $event),
            TicketEventType::STATUS_CHANGED => $this->changeStatus($ticket, $event),
            default => $this->retarget($ticket, $event->created_at),
        };
    }

    protected function meetFirstResponse(Ticket $ticket, TicketEvent $event): void
    {
        $target = $ticket->slaTargets->firstWhere('metric', SlaMetric::FIRST_RESPONSE);

        if ($target !== null && $target->achieved_at === null && $event->actor instanceof User) {
            $target->update(['achieved_at' => $event->created_at]);
        }
    }

    protected function changeStatus(Ticket $ticket, TicketEvent $event): void
    {
        $target = $ticket->slaTargets->firstWhere('metric', SlaMetric::RESOLUTION);

        if ($target === null) {
            return;
        }

        $to = TicketStatus::from($event->properties['to']);
        $at = $event->created_at;
        $schedule = $ticket->effectiveSlaPolicy()?->businessSchedule;

        // Time spent met counts like a pause: the clock resumes with the time already used.
        if ($target->achieved_at !== null && ! $to->isResolved()) {
            $target->paused_business_minutes += BusinessSchedule::minutesBetweenFor($schedule, $target->achieved_at, $at);
            $target->achieved_at = null;
        }

        if ($target->paused_at !== null && ! $to->pausesSla()) {
            $target->paused_business_minutes += BusinessSchedule::minutesBetweenFor($schedule, $target->paused_at, $at);
            $target->paused_at = null;
        }

        if ($to->pausesSla() && $target->paused_at === null) {
            $target->paused_at = $at;
        }

        if ($to->isResolved() && $target->achieved_at === null) {
            $target->achieved_at = $at;
        }

        $target->recalculate($schedule, $ticket->effectiveSlaPolicy()?->warning_percent ?? 75);
        $target->save();
    }

    /**
     * Create, retarget or delete each metric's target to match the ticket's current policy (its own, else the desk's) and the
     * ticket's priority. Met targets are left alone, and a breached one is never deleted.
     */
    protected function retarget(Ticket $ticket, CarbonInterface $at): void
    {
        $policy = $ticket->effectiveSlaPolicy();

        foreach (SlaMetric::cases() as $metric) {
            $minutes = $policy?->targetFor($ticket->priority, $metric);
            $target = $ticket->slaTargets->firstWhere('metric', $metric);

            if ($target?->achieved_at !== null) {
                continue;
            }

            if ($target === null && $minutes !== null) {
                $this->start($ticket, $policy, $metric, $minutes, $at);
            } elseif ($target !== null && $minutes === null && $target->breached_at === null) {
                $target->delete();
            } elseif ($target !== null && $minutes !== null) {
                $target->target_minutes = $minutes;
                $target->recalculate($policy->businessSchedule, $policy->warning_percent);
                $target->save();
            }
        }
    }

    /**
     * Start a clock at the ticket's creation. Resolution starts paused or met when the ticket is in
     * such a status.
     * ponytail: first response isn't marked met when an agent already replied before a desk move
     * brought the policy in; check the comments here if that matters.
     */
    protected function start(Ticket $ticket, SlaPolicy $policy, SlaMetric $metric, int $minutes, CarbonInterface $at): void
    {
        $isResolution = $metric === SlaMetric::RESOLUTION;

        $target = new TicketSlaTarget([
            'ticket_id' => $ticket->getKey(),
            'metric' => $metric,
            'target_minutes' => $minutes,
            'started_at' => $ticket->created_at,
            'paused_at' => $isResolution && $ticket->status->pausesSla() ? $at : null,
            'achieved_at' => $isResolution && $ticket->status->isResolved() ? $at : null,
        ]);
        $target->recalculate($policy->businessSchedule, $policy->warning_percent);
        $target->save();
    }
}
