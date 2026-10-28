<?php

namespace App\Console\Commands;

use App\Actions\Tickets\RecomputeTicketMetrics as RecomputeTicketMetricsAction;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:recompute-metrics')]
#[Description('Recompute every workspace\'s ticket reporting metrics')]
class RecomputeTicketMetrics extends Command
{
    public function handle(RecomputeTicketMetricsAction $recomputeTicketMetrics): void
    {
        Workspace::all()->eachCurrent(function () use ($recomputeTicketMetrics): void {
            Ticket::query()
                ->with(['slaPolicy.businessSchedule', 'desk.slaPolicy.businessSchedule'])
                ->lazyById()
                ->each($recomputeTicketMetrics->handle(...));
        });
    }
}
