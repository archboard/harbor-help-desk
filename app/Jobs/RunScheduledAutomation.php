<?php

namespace App\Jobs;

use App\Actions\Automations\RunScheduledAutomation as RunScheduledAutomationAction;
use App\Models\Automation;
use App\Models\Ticket;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Run a swept automation on one ticket. Tenant-aware by the multitenancy default, and serialized
 * per ticket with {@see RunAutomations}.
 */
class RunScheduledAutomation implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $maxExceptions = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Automation $automation, public Ticket $ticket)
    {
        $this->onQueue('automations');
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    /**
     * Not on the sync connection, for the reason given at {@see RunAutomations::middleware()}.
     *
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        if (config('queue.default') === 'sync') {
            return [];
        }

        return [(new WithoutOverlapping("ticket:{$this->ticket->getKey()}"))->releaseAfter(5)->expireAfter(180)];
    }

    public function handle(RunScheduledAutomationAction $runScheduledAutomation): void
    {
        $runScheduledAutomation->handle($this->automation, $this->ticket);
    }
}
