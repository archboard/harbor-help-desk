<?php

namespace App\Events;

use App\Enums\AutomationRunStatus;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A ping that an automation's run finished or started waiting. It carries ids and the status
 * only, so clients reload the run history through their own policies.
 */
class AutomationRunChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public string $automation_id,
        public string $run_id,
        public AutomationRunStatus $status,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("automations.{$this->automation_id}")];
    }

    public function broadcastAs(): string
    {
        return 'AutomationRunChanged';
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    /**
     * @return array{automation_id: string, run_id: string, status: string}
     */
    public function broadcastWith(): array
    {
        return [
            'automation_id' => $this->automation_id,
            'run_id' => $this->run_id,
            'status' => $this->status->value,
        ];
    }
}
