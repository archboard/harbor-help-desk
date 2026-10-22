<?php

namespace App\Console\Commands;

use App\Actions\Automations\SweepScheduledAutomations;
use App\Models\Automation;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automations:sweep')]
#[Description('Run every workspace\'s scheduled automations that are due')]
class SweepAutomations extends Command
{
    public function handle(SweepScheduledAutomations $sweepScheduledAutomations): void
    {
        $dueWorkspaceIds = Automation::withoutGlobalScope('workspace')
            ->where('enabled', true)->where('trigger_type', 'schedule')->get()
            ->filter(fn (Automation $automation): bool => $automation->dueSweepMoment() !== null)
            ->pluck('workspace_id')->unique()->all();

        Workspace::query()->whereIn('id', $dueWorkspaceIds)->get()->eachCurrent(function () use ($sweepScheduledAutomations): void {
            $sweepScheduledAutomations->handle();
        });
    }
}
