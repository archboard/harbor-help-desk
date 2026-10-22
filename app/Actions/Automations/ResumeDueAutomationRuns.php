<?php

namespace App\Actions\Automations;

use App\Enums\AutomationRunStatus;
use App\Jobs\ResumeAutomationRun;
use App\Models\AutomationRun;
use Illuminate\Support\Facades\DB;

class ResumeDueAutomationRuns
{
    /**
     * Claim the current workspace's waiting runs that are due and queue a job for each. Claiming
     * flips them to `running` under `for update skip locked`, so two commands never take the same run.
     *
     * ponytail: a claimed run whose job dies stays `running` with its `resume_at` set; add a sweep
     * that re-queues those once they are older than the job's retry window if that ever happens.
     * One batch of 100 per call.
     *
     * @return int How many runs were queued.
     */
    public function handle(): int
    {
        $runs = DB::transaction(function () {
            $runs = AutomationRun::query()->where('status', AutomationRunStatus::WAITING)->where('resume_at', '<=', now())
                ->orderBy('resume_at')->limit(100)->lock('for update skip locked')->get();

            $runs->each(fn (AutomationRun $run) => $run->update(['status' => AutomationRunStatus::RUNNING]));

            return $runs;
        });

        $runs->each(fn (AutomationRun $run) => ResumeAutomationRun::dispatch($run));

        return $runs->count();
    }
}
