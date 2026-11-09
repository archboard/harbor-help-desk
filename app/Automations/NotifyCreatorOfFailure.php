<?php

namespace App\Automations;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Notifications\AutomationNotice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the automation's creator that a run failed, at most once per automation per hour (a
 * cache key, so a failing automation doesn't flood them). Nobody is told when the creator is gone,
 * left the workspace or may no longer manage the automation, and that doesn't use up the hour.
 */
class NotifyCreatorOfFailure
{
    public function handle(Automation $automation, AutomationRun $run): void
    {
        $creator = $automation->loadMissing(['createdBy', 'workspace'])->createdBy;

        if ($creator === null
            || ! $automation->workspace->users()->whereKey($creator->getKey())->exists()
            || Gate::forUser($creator)->denies('update', $automation)
            || ! Cache::add("automation-failure-notice:{$automation->getKey()}", true, now()->addHour())) {
            return;
        }

        Notification::sendNow($creator, new AutomationNotice(
            $automation,
            $run->ticket,
            __('Automation failed: :name', ['name' => $automation->name]),
            __('“:name” failed: :error', ['name' => $automation->name, 'error' => $run->error]),
        ));
    }
}
