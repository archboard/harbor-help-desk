<?php

namespace App\Listeners;

use App\Actions\Operations\OperatorRecipients;
use App\Notifications\JobFailedNotification;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Mails the operators when a queued job fails, at most once an hour per job class. Not
 * queued on purpose: it reports on the queue itself.
 */
class NotifyOperatorsOfFailedJob
{
    public function handle(JobFailed $event): void
    {
        $jobName = $event->job->resolveName();

        if (! Cache::add('harbor:job-failed:'.$jobName, true, now()->addHour())) {
            return;
        }

        $emails = app(OperatorRecipients::class)->emails();

        if ($emails === []) {
            return;
        }

        // A broken mailer must not take the failing worker down with it.
        rescue(fn () => Notification::route('mail', $emails)->notify(new JobFailedNotification(
            $jobName,
            $event->connectionName,
            $event->job->getQueue(),
            $event->exception->getMessage(),
        )));
    }
}
