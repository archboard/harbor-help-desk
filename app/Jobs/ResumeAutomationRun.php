<?php

namespace App\Jobs;

use App\Actions\Automations\ResumeAutomationRun as ResumeAutomationRunAction;
use App\Models\AutomationRun;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Continue a waiting automation run that is due. Tenant-aware by the multitenancy default, and
 * serialized per ticket with {@see RunAutomations}.
 */
class ResumeAutomationRun implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $maxExceptions = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public AutomationRun $run)
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

        return [(new WithoutOverlapping("ticket:{$this->run->ticket_id}"))->releaseAfter(5)->expireAfter(180)];
    }

    public function handle(ResumeAutomationRunAction $resumeAutomationRun): void
    {
        $resumeAutomationRun->handle($this->run);
    }
}
