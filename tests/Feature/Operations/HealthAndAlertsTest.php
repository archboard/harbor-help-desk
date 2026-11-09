<?php

use App\Actions\Install\CheckServices;
use App\Enums\WorkspaceRole;
use App\Listeners\FailHealthCheckWhenServicesAreDown;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\JobFailedNotification;
use App\Notifications\QueueWaitNotification;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Horizon\Events\LongWaitDetected;

function failedJob(string $name = 'App\Jobs\Example'): JobFailed
{
    $job = Mockery::mock(Job::class)->shouldIgnoreMissing();
    $job->shouldReceive('resolveName')->andReturn($name);
    $job->shouldReceive('getQueue')->andReturn('default');

    return new JobFailed('redis', $job, new RuntimeException('Boom'));
}

test('/up is healthy, and its listener throws (so /up returns 500) when the database is unreachable', function () {
    $this->get('/up')->assertOk();

    config(['database.default' => 'missing']);

    expect(fn () => app(FailHealthCheckWhenServicesAreDown::class)->handle(new DiagnosingHealth))->toThrow(InvalidArgumentException::class);

    config(['database.default' => 'pgsql']);
});

test('the scheduler check fails without a fresh heartbeat', function () {
    $scheduler = fn () => collect(app(CheckServices::class)->handle())->firstWhere('name', 'Scheduler');

    expect($scheduler()->ok)->toBeFalse();

    Cache::put('harbor:scheduler-heartbeat', now()->subMinutes(4)->getTimestamp());
    expect($scheduler()->ok)->toBeFalse();

    Cache::put('harbor:scheduler-heartbeat', now()->subMinute()->getTimestamp());
    expect($scheduler()->ok)->toBeTrue();
});

test('a failed job notifies the operators once an hour per job class', function () {
    Notification::fake();
    config(['harbor.multitenant' => true, 'harbor.horizon_emails' => ['ops@example.com']]);

    event(failedJob());
    event(failedJob());
    event(failedJob('App\Jobs\Other'));

    Notification::assertSentOnDemandTimes(JobFailedNotification::class, 2);
    Notification::assertSentOnDemand(JobFailedNotification::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === ['ops@example.com']);

    $this->travel(61)->minutes();
    event(failedJob());

    Notification::assertSentOnDemandTimes(JobFailedNotification::class, 3);
});

test('self-hosted alerts go to the workspace admins, not agents', function () {
    config(['harbor.multitenant' => false]);
    $workspace = Workspace::factory()->create();
    $owner = User::factory()->create();
    $agent = User::factory()->create();
    $workspace->users()->attach($owner, ['role' => WorkspaceRole::OWNER]);
    $workspace->users()->attach($agent, ['role' => WorkspaceRole::AGENT]);

    Notification::fake();
    event(failedJob());

    Notification::assertSentOnDemand(JobFailedNotification::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === [$owner->email]);
});

test('Horizon wait alerts are routed to the operators', function () {
    config(['harbor.multitenant' => true, 'harbor.horizon_emails' => ['ops@example.com']]);
    Notification::fake();

    event(new LongWaitDetected('redis', 'default', 120));
    event(new LongWaitDetected('redis', 'default', 130));

    Notification::assertSentOnDemandTimes(QueueWaitNotification::class, 1);
    Notification::assertSentOnDemand(QueueWaitNotification::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === ['ops@example.com']);
});
