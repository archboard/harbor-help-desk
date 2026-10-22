<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Models\TicketSlaTarget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CheckSlaTargets
{
    /**
     * Warn about or breach the current workspace's SLA clocks that ran out. Rows are claimed with
     * SKIP LOCKED and stamped before the event, so overlapping runs never report one twice. A
     * clock already past due that was never warned about is breached without a warning.
     *
     * @return int The number of targets claimed.
     */
    public function handle(int $limit = 100): int
    {
        return DB::transaction(function () use ($limit): int {
            $targets = TicketSlaTarget::query()
                ->whereNull('achieved_at')
                ->whereNull('paused_at')
                ->whereNull('breached_at')
                ->whereHas('ticket')
                ->where(fn (Builder $query) => $query
                    ->where('due_at', '<=', now())
                    ->orWhere(fn (Builder $query) => $query->where('warn_at', '<=', now())->whereNull('warned_at')))
                ->orderBy('due_at')
                ->limit($limit)
                ->lock('for update skip locked')
                ->with('ticket')
                ->get();

            foreach ($targets as $target) {
                $breached = $target->due_at <= now();

                $target->update([$breached ? 'breached_at' : 'warned_at' => now()]);

                $target->ticket->recordEvent(
                    $breached ? TicketEventType::SLA_BREACHED : TicketEventType::SLA_WARNING,
                    null,
                    ['metric' => $target->metric->value, 'due_at' => $target->due_at->toIso8601String()],
                );
            }

            return $targets->count();
        });
    }
}
