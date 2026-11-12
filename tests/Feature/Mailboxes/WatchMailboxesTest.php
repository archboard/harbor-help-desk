<?php

use App\Data\Mailboxes\MailboxData;
use App\Mail\Inbound\ImapMailboxClient;
use App\Mail\Inbound\MailboxWatcher;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    config(['harbor.mail.watch' => true]);
    $this->mailbox = Mailbox::factory()->create(['default_desk_id' => Desk::sole()->id, 'imap_uid_validity' => 7, 'imap_last_uid' => 100]);
});

test('a moved UIDNEXT queues a poll in the mailbox workspace', function () {
    config(['queue.default' => 'database']);
    mockImapClient(uidValidity: 7, uidNext: 102);
    Workspace::forgetCurrent();

    (new MailboxWatcher)->tick();

    $payload = DB::table('jobs')->sole();
    expect($payload->queue)->toBe('mail-poll')
        ->and($payload->payload)->toContain('PollMailbox')
        ->and($payload->payload)->toContain($this->workspace->id);
});

test('an unchanged UIDNEXT queues nothing, and a change is queued once', function () {
    config(['queue.default' => 'database']);
    $client = Mockery::mock(ImapMailboxClient::class);
    $client->shouldReceive('status')->andReturn(
        ['uid_validity' => 7, 'uid_next' => 101],
        ['uid_validity' => 7, 'uid_next' => 102],
        ['uid_validity' => 7, 'uid_next' => 102],
    );
    app()->bind(ImapMailboxClient::class, fn () => $client);
    $watcher = new MailboxWatcher;

    $watcher->tick();
    expect(DB::table('jobs')->count())->toBe(0);

    $watcher->tick();
    DB::table('jobs')->delete();
    $watcher->tick();
    expect(DB::table('jobs')->count())->toBe(0);
});

test('shards split the mailboxes with no overlap', function () {
    Mailbox::factory()->count(9)->create(['default_desk_id' => Desk::sole()->id]);
    $watched = [];
    app()->bind(ImapMailboxClient::class, function ($app, array $parameters) use (&$watched) {
        $watched[] = $parameters['mailbox']->id;

        return tap(Mockery::mock(ImapMailboxClient::class), fn ($client) => $client->shouldReceive('status')->andThrow(new RuntimeException('offline')));
    });

    foreach ([0, 1, 2] as $shard) {
        (new MailboxWatcher($shard, 3))->tick();
    }

    expect($watched)->toHaveCount(10)
        ->and(array_unique($watched))->toEqualCanonicalizing(Mailbox::pluck('id')->all());
});

test('a connection error is saved and the mailbox reconnects after a backoff', function () {
    $failing = Mockery::mock(ImapMailboxClient::class);
    $failing->shouldReceive('status')->once()->andThrow(new RuntimeException('Connection refused'));
    $failing->shouldReceive('disconnect');
    $clients = [$failing, mockImapClient(uidValidity: 7, uidNext: 101)];
    app()->bind(ImapMailboxClient::class, function () use (&$clients) {
        return array_shift($clients);
    });
    $watcher = new MailboxWatcher;

    $watcher->tick();
    expect($this->mailbox->fresh()->last_error)->toBe('Connection refused');

    $watcher->tick();
    expect($clients)->toHaveCount(1);

    $this->travel(31)->seconds();
    $watcher->tick();
    expect($clients)->toHaveCount(0);
});

test('the scheduled poll becomes a 5-minute backstop while watching', function (bool $watch, ?int $polledMinutesAgo, bool $due) {
    config(['harbor.mail.watch' => $watch]);
    $this->mailbox->update(['polled_at' => $polledMinutesAgo === null ? null : now()->subMinutes($polledMinutesAgo)]);

    expect(Mailbox::needsScheduledPoll()->exists())->toBe($due);
})->with([
    'not watching' => [false, 1, true],
    'watching, polled recently' => [true, 1, false],
    'watching, polled long ago' => [true, 6, true],
    'watching, never polled' => [true, null, true],
]);

test('the watch command refuses to run unless watching is turned on', function () {
    config(['harbor.mail.watch' => false]);

    $this->artisan('mailboxes:watch')->assertFailed();
});

test('Microsoft mailboxes are not watched, and still polled every minute without push', function () {
    $microsoft = Mailbox::factory()->microsoft()->create(['default_desk_id' => Desk::sole()->id, 'polled_at' => now()->subMinute()]);
    $watched = [];
    app()->bind(ImapMailboxClient::class, function ($app, array $parameters) use (&$watched) {
        $watched[] = $parameters['mailbox']->id;

        return mockImapClient(uidValidity: 7, uidNext: 101);
    });
    $this->mailbox->update(['polled_at' => now()->subMinute()]);

    (new MailboxWatcher)->tick();

    expect($watched)->toBe([$this->mailbox->id])
        ->and(Mailbox::needsScheduledPoll()->pluck('id')->all())->toBe([$microsoft->id])
        ->and(MailboxData::from($microsoft)->receivesBy)->toBe('poll');

    $microsoft->update(['push_expires_at' => now()->addDay()]);
    expect(Mailbox::needsScheduledPoll()->exists())->toBeFalse();
});
