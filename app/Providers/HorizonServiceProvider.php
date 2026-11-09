<?php

namespace App\Providers;

use App\Actions\Operations\OperatorRecipients;
use App\Models\User;
use App\Notifications\QueueWaitNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Laravel\Horizon\Events\LongWaitDetected;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Mail Horizon's long-wait alert to the operators. Horizon's own mail route takes one fixed
     * address and the self-hosted admins live in the database, so it stays unset and this sends
     * the alert instead, at most once per queue every 5 minutes as Horizon does.
     */
    public function boot(): void
    {
        parent::boot();

        Event::listen(LongWaitDetected::class, function (LongWaitDetected $event): void {
            $notification = new QueueWaitNotification($event->connection, $event->queue, $event->seconds);
            $emails = app(OperatorRecipients::class)->emails();

            if ($emails !== [] && Cache::add('harbor:horizon-alert:'.$notification->signature(), true, now()->addMinutes(5))) {
                rescue(fn () => Notification::route('mail', $emails)->notify($notification));
            }
        });
    }

    /**
     * Register the Horizon gate.
     *
     * Horizon shows every tenant's jobs, so in multitenant mode only the platform operators on
     * the allowlist may see it. Self-hosted, the single workspace's owners and admins may.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (User $user): bool => app(OperatorRecipients::class)->includes($user));
    }
}
