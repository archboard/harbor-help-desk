<?php

namespace App\Jobs;

use App\Actions\Automations\RunAutomationsForEvent;
use App\Models\TicketEvent;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Run the automations a ticket event triggers. Tenant-aware by the multitenancy default, so it
 * runs inside the event's workspace. Jobs of one ticket never overlap, so they run in order and
 * see each other's changes.
 */
class RunAutomations implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $maxExceptions = 3;

    /**
     * An event or ticket deleted before the job ran has nothing to run on.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public TicketEvent $ticketEvent)
    {
        $this->onQueue('automations');
    }

    /**
     * Released jobs count as attempts, so they're given a time limit instead of a number of tries.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    /**
     * Not on the sync connection: nothing runs in parallel there, and the lock would make the job
     * an automation's own action dispatches wait for (and be dropped by) the job that is running it.
     *
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        if (config('queue.default') === 'sync') {
            return [];
        }

        return [(new WithoutOverlapping("ticket:{$this->ticketEvent->ticket_id}"))->releaseAfter(5)->expireAfter(180)];
    }

    public function handle(RunAutomationsForEvent $runAutomationsForEvent): void
    {
        $runAutomationsForEvent->handle($this->ticketEvent);
    }
}
