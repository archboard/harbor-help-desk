<?php

namespace App\Console\Commands;

use App\Actions\Automations\ResumeDueAutomationRuns;
use App\Enums\AutomationRunStatus;
use App\Models\AutomationRun;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automations:resume')]
#[Description('Resume every workspace\'s waiting automation runs that are due')]
class ResumeAutomations extends Command
{
    public function handle(ResumeDueAutomationRuns $resumeDueAutomationRuns): void
    {
        $dueWorkspaceIds = AutomationRun::withoutGlobalScope('workspace')
            ->select('workspace_id')
            ->where('status', AutomationRunStatus::WAITING)
            ->where('resume_at', '<=', now());

        Workspace::query()->whereIn('id', $dueWorkspaceIds)->get()->eachCurrent(function () use ($resumeDueAutomationRuns): void {
            $resumeDueAutomationRuns->handle();
        });
    }
}
