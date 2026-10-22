<?php

namespace App\Jobs;

use App\Actions\Automations\RunWebhookAutomation as RunWebhookAutomationAction;
use App\Models\AutomationRun;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Run a webhook automation on its ticket with the payload stored on the run. Tenant-aware by the
 * multitenancy default, and serialized per ticket with {@see RunAutomations}.
 */
class RunWebhookAutomation implements ShouldQueue
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

    public function handle(RunWebhookAutomationAction $runWebhookAutomation): void
    {
        $runWebhookAutomation->handle($this->run);
    }
}
