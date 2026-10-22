<?php

namespace App\Automations;

use App\Models\Ticket;
use Closure;

/**
 * Which automation run is executing right now, so {@see Ticket::recordEvent()} can
 * stamp the events its actions cause. Bound as a scoped instance: it lives for one request or
 * queued job and holds an id only, never tenant data. The previous value is always restored,
 * also when the callback throws and when runs nest (a queue that runs jobs inline).
 */
class RunCausation
{
    public ?string $runId = null;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function during(string $runId, Closure $callback): mixed
    {
        $previous = $this->runId;
        $this->runId = $runId;

        try {
            return $callback();
        } finally {
            $this->runId = $previous;
        }
    }
}
