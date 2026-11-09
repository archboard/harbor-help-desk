<?php

use App\Actions\Mailboxes\SubscribeMailboxPush;
use App\Enums\MailboxDriver;
use App\Jobs\PollMailbox;
use App\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->freezeSecond();
    config([
        'app.url' => 'https://harbor.test',
        'harbor.mail.push_secret' => 'push-secret',
        'services.google_mail.pubsub_topic' => 'projects/harbor/topics/gmail',
    ]);

    $this->acme = Workspace::factory()->create();
    $this->acme->execute(fn () => Mailbox::factory()->create([
        'driver' => MailboxDriver::GOOGLE,
        'address' => 'help@acme.com',
        'settings' => ['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_at' => now()->addHour()->getTimestamp()],
    ]));
    $this->mailbox = Mailbox::withoutGlobalScope('workspace')->sole();
});

/**
 * A Google-driver mailbox in the given workspace, with a token that is still fresh.
 */
function googleMailbox(Workspace $workspace, string $address): Mailbox
{
    return $workspace->execute(fn () => Mailbox::factory()->create([
        'driver' => MailboxDriver::GOOGLE,
        'address' => $address,
        'settings' => ['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_at' => now()->addHour()->getTimestamp()],
    ]));
}

/**
 * A Pub/Sub push payload as Google posts it.
 *
 * @return array<string, mixed>
 */
function gmailNotification(string $email): array
{
    return ['message' => ['data' => base64_encode((string) json_encode(['emailAddress' => $email, 'historyId' => '12345']))]];
}

/**
 * A Microsoft Graph notification payload, signed for the mailbox unless a bad hmac is given.
 *
 * @return array<string, mixed>
 */
function graphNotification(Mailbox $mailbox, ?string $lifecycleEvent = null, ?string $hmac = null): array
{
    return ['value' => [[
        'subscriptionId' => $mailbox->push_subscription_id ?? 'sub-1',
        'clientState' => $mailbox->id.'.'.($hmac ?? hash_hmac('sha256', $mailbox->id, (string) $mailbox->push_secret)),
        'lifecycleEvent' => $lifecycleEvent,
        'resource' => "me/mailFolders('Inbox')/messages",
    ]]];
}

test('subscribing a Gmail mailbox stores the watch expiry', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['historyId' => '12345', 'expiration' => now()->addDays(6)->getTimestampMs()])]);

    app(SubscribeMailboxPush::class)->handle($this->mailbox);

    expect($this->mailbox->fresh()->push_expires_at->toDateTimeString())->toBe(now()->addDays(6)->toDateTimeString());
    Http::assertSent(fn ($request) => str_contains($request->url(), 'gmail/v1/users/me/watch')
        && $request['topicName'] === 'projects/harbor/topics/gmail'
        && $request['labelIds'] === ['INBOX']
        && $request->hasHeader('Authorization', 'Bearer access-1'));
});

/**
 * Microsoft's token endpoint (for the Graph-scoped token) and Graph's subscriptions endpoint.
 */
function fakeGraph(string $subscriptionId = 'sub-1'): void
{
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'graph-token']),
        'graph.microsoft.com/*' => Http::response(['id' => $subscriptionId]),
    ]);
}

test('subscribing a Microsoft mailbox stores the subscription id, expiry and secret', function () {
    fakeGraph();
    $mailbox = $this->acme->execute(fn () => Mailbox::factory()->create([
        'driver' => MailboxDriver::MICROSOFT,
        'settings' => ['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_at' => now()->addHour()->getTimestamp()],
    ]));

    app(SubscribeMailboxPush::class)->handle($mailbox);

    expect($mailbox->fresh())
        ->push_subscription_id->toBe('sub-1')
        ->push_expires_at->not->toBeNull()
        ->push_secret->not->toBeNull();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'login.microsoftonline.com')
        && $request['grant_type'] === 'refresh_token'
        && str_contains($request['scope'], 'https://graph.microsoft.com/Mail.Read'));
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer graph-token')
        && $request['notificationUrl'] === 'https://harbor.test/webhooks/microsoft/graph'
        && $request['lifecycleNotificationUrl'] === 'https://harbor.test/webhooks/microsoft/graph'
        && $request['clientState'] === $mailbox->id.'.'.hash_hmac('sha256', $mailbox->id, (string) $mailbox->fresh()->push_secret)
        && $request['resource'] === "me/mailFolders('Inbox')/messages");
});

test('push stays off without provider config or a public app url', function (array $config) {
    config($config);
    Http::fake();

    app(SubscribeMailboxPush::class)->handle($this->mailbox);

    expect($this->mailbox->fresh()->push_expires_at)->toBeNull();
    Http::assertNothingSent();
})->with([
    'no push secret' => [['harbor.mail.push_secret' => null]],
    'no Pub/Sub topic' => [['services.google_mail.pubsub_topic' => null]],
    'local app url' => [['app.url' => 'http://localhost']],
]);

test('the Gmail webhook ignores a bad token, and is off without a configured secret', function () {
    Queue::fake();

    $this->postJson('/webhooks/google/gmail?token=wrong', gmailNotification('help@acme.com'))->assertNoContent();

    config(['harbor.mail.push_secret' => null]);
    $this->postJson('/webhooks/google/gmail?token=', gmailNotification('help@acme.com'))->assertNoContent();

    Queue::assertNothingPushed();
});

test('the Gmail webhook dispatches a poll for the matching mailbox only', function () {
    Queue::fake();
    $globex = Workspace::factory()->create();
    googleMailbox($globex, 'help@globex.com');

    $this->postJson('/webhooks/google/gmail?token=push-secret', gmailNotification('HELP@acme.com'))->assertNoContent();

    Queue::assertPushed(PollMailbox::class, fn ($job) => $job->mailbox->is($this->mailbox));
    Queue::assertPushed(PollMailbox::class, 1);
    expect(Workspace::current())->toBeNull();
});

test('the Graph handshake echoes the validation token as plain text', function () {
    $this->post('/webhooks/microsoft/graph?validationToken=token-123')
        ->assertOk()
        ->assertContent('token-123')
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');
});

test('a Graph notification with a bad hmac is ignored, a good one dispatches a poll', function () {
    Queue::fake();
    $this->mailbox->forceFill(['push_secret' => 'secret-1', 'push_subscription_id' => 'sub-1'])->save();
    $globex = googleMailbox(Workspace::factory()->create(), 'help@globex.com');
    $globex->forceFill(['push_secret' => 'secret-2'])->save();

    $this->postJson('/webhooks/microsoft/graph', graphNotification($this->mailbox, hmac: 'forged'))->assertAccepted();
    $this->postJson('/webhooks/microsoft/graph', ['value' => [['clientState' => 'garbage']]])->assertAccepted();
    Queue::assertNothingPushed();

    // A valid hmac for acme's mailbox never polls globex's.
    $this->postJson('/webhooks/microsoft/graph', graphNotification($this->mailbox))->assertAccepted();
    Queue::assertPushed(PollMailbox::class, 1);
    Queue::assertPushed(PollMailbox::class, fn ($job) => $job->mailbox->is($this->mailbox) && ! $job->mailbox->is($globex));
    expect(Workspace::current())->toBeNull();
});

test('a Graph lifecycle notification renews the subscription', function () {
    fakeGraph('sub-2');
    $mailbox = $this->acme->execute(fn () => Mailbox::factory()->create([
        'driver' => MailboxDriver::MICROSOFT,
        'settings' => ['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_at' => now()->addHour()->getTimestamp()],
        'push_secret' => 'secret-1',
        'push_subscription_id' => 'sub-1',
    ]));

    $this->postJson('/webhooks/microsoft/graph', graphNotification($mailbox, lifecycleEvent: 'reauthorizationRequired'))->assertAccepted();

    expect($mailbox->fresh()->push_subscription_id)->toBe('sub-2');
});

test('renewal picks only the subscriptions about to expire', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['historyId' => '9', 'expiration' => now()->addDays(7)->getTimestampMs()])]);
    $this->mailbox->forceFill(['push_expires_at' => now()->addDay()])->save();
    $fresh = googleMailbox($this->acme, 'fresh@acme.com');
    $fresh->forceFill(['push_expires_at' => now()->addDays(3)])->save();
    $unsubscribed = googleMailbox($this->acme, 'idle@acme.com');

    $this->artisan('mailboxes:renew-push')->assertSuccessful();

    Http::assertSentCount(1);
    expect($this->mailbox->fresh()->push_expires_at->toDateTimeString())->toBe(now()->addDays(7)->toDateTimeString())
        ->and($fresh->fresh()->push_expires_at->toDateTimeString())->toBe(now()->addDays(3)->toDateTimeString())
        ->and($unsubscribed->fresh()->push_expires_at)->toBeNull();
});

test('a failed subscription clears the push columns and falls back to polling', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response('quota exceeded', 500)]);
    $this->mailbox->forceFill(['push_expires_at' => now()->addDays(5)])->save();

    app(SubscribeMailboxPush::class)->handle($this->mailbox);

    expect($this->mailbox->fresh())
        ->push_subscription_id->toBeNull()
        ->push_expires_at->toBeNull()
        ->push_secret->toBeNull()
        ->last_error->not->toBeNull()
        ->and($this->acme->execute(fn () => Mailbox::needsScheduledPoll()->whereKey($this->mailbox->id)->exists()))->toBeTrue();
});

test('a mailbox with live push is only polled as the 5-minute backstop', function () {
    $this->mailbox->forceFill(['push_expires_at' => now()->addDays(5), 'polled_at' => now()->subMinute()])->save();
    $due = fn () => $this->acme->execute(fn () => Mailbox::needsScheduledPoll()->whereKey($this->mailbox->id)->exists());

    expect($due())->toBeFalse();

    $this->mailbox->update(['polled_at' => now()->subMinutes(6)]);
    expect($due())->toBeTrue();

    // Once push lapses, normal per-minute polling resumes.
    $this->mailbox->update(['push_expires_at' => now()->subHour(), 'polled_at' => now()->subMinute()]);
    expect($due())->toBeTrue();
});
