<?php

use App\Actions\Email\ReceiveInboundEmail;
use App\Enums\MailboxDriver;
use App\Jobs\PollMailbox;
use App\Mail\Inbound\GraphMailboxClient;
use App\Mail\Inbound\ImapMailboxClient;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\Workspace;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->mailbox = Mailbox::factory()->create(['default_desk_id' => Desk::sole()->id]);
});

function rawFixtureEmail(string $name): string
{
    return (string) file_get_contents(__DIR__."/../../Fixtures/emails/{$name}.eml");
}

test('polls run on the mail-poll queue', function () {
    Queue::fake();

    PollMailbox::dispatch($this->mailbox);

    Queue::assertPushedOn('mail-poll', PollMailbox::class);
});

test('the first poll starts at the newest message without importing old mail', function () {
    mockImapClient(uidValidity: 7, uidNext: 101)->shouldNotReceive('fetchSince');

    PollMailbox::dispatchSync($this->mailbox);

    expect($this->mailbox->fresh())
        ->imap_uid_validity->toBe(7)
        ->imap_last_uid->toBe(100)
        ->polled_at->not->toBeNull();
});

test('an unchanged UIDNEXT fetches nothing', function () {
    $this->mailbox->update(['imap_uid_validity' => 7, 'imap_last_uid' => 100]);
    mockImapClient(uidValidity: 7, uidNext: 101)->shouldNotReceive('fetchSince');

    PollMailbox::dispatchSync($this->mailbox);

    expect($this->mailbox->fresh()->imap_last_uid)->toBe(100);
});

test('new messages are imported and the cursor advances', function () {
    $this->mailbox->update(['imap_uid_validity' => 7, 'imap_last_uid' => 100]);
    mockImapClient(uidValidity: 7, uidNext: 103)
        ->shouldReceive('fetchSince')->with(100)->andReturn([101 => rawFixtureEmail('html-only'), 102 => rawFixtureEmail('outlook-reply')]);

    PollMailbox::dispatchSync($this->mailbox);

    expect(Ticket::oldest('id')->pluck('subject')->all())->toBe(['Broken link', 'RE: Printer jam']);
    expect(Ticket::first()->mailbox_id)->toBe($this->mailbox->id);
    expect($this->mailbox->fresh()->imap_last_uid)->toBe(102);
});

test('a message that fails to import is skipped so it cannot block the mailbox', function () {
    $this->mailbox->update(['imap_uid_validity' => 7, 'imap_last_uid' => 100]);
    mockImapClient(uidValidity: 7, uidNext: 103)
        ->shouldReceive('fetchSince')->andReturn([101 => rawFixtureEmail('html-only'), 102 => rawFixtureEmail('outlook-reply')]);
    $receive = $this->mock(ReceiveInboundEmail::class);
    $receive->shouldReceive('handle')->once()->andThrow(new RuntimeException('Boom'));
    $receive->shouldReceive('handle')->once()->andReturnNull();

    PollMailbox::dispatchSync($this->mailbox);

    expect($this->mailbox->fresh())
        ->imap_last_uid->toBe(102)
        ->last_error->toBeNull();
});

test('a changed UIDVALIDITY resets the cursor to the newest message', function () {
    $this->mailbox->update(['imap_uid_validity' => 7, 'imap_last_uid' => 100]);
    mockImapClient(uidValidity: 8, uidNext: 5)->shouldNotReceive('fetchSince');

    PollMailbox::dispatchSync($this->mailbox);

    expect($this->mailbox->fresh())
        ->imap_uid_validity->toBe(8)
        ->imap_last_uid->toBe(4);
});

test('a connection error is saved on the mailbox and cleared by the next good poll', function () {
    $client = Mockery::mock(ImapMailboxClient::class);
    $client->shouldReceive('status')->andThrow(new RuntimeException('Authentication failed'));
    $client->shouldReceive('disconnect');
    app()->bind(ImapMailboxClient::class, fn () => $client);

    PollMailbox::dispatchSync($this->mailbox);
    expect($this->mailbox->fresh()->last_error)->toBe('Authentication failed');

    mockImapClient(uidValidity: 7, uidNext: 1);
    PollMailbox::dispatchSync($this->mailbox->fresh());
    expect($this->mailbox->fresh()->last_error)->toBeNull();
});

test('the command queues polls only for each workspace own non-hosted mailboxes', function () {
    Queue::fake();
    $hosted = Mailbox::factory()->create(['driver' => MailboxDriver::HOSTED, 'settings' => null]);
    $other = Workspace::factory()->create()->execute(fn () => Mailbox::factory()->create());

    $this->artisan('mailboxes:poll')->assertSuccessful();

    Queue::assertPushed(PollMailbox::class, 2);
    Queue::assertPushed(PollMailbox::class, fn (PollMailbox $job) => $job->mailbox->is($this->mailbox));
    Queue::assertPushed(PollMailbox::class, fn (PollMailbox $job) => $job->mailbox->is($other));
    Queue::assertNotPushed(PollMailbox::class, fn (PollMailbox $job) => $job->mailbox->is($hosted));
});

test('the command queues each poll with its workspace attached', function () {
    config(['queue.default' => 'database']);
    Workspace::forgetCurrent();

    $this->artisan('mailboxes:poll')->assertSuccessful();

    expect(DB::table('jobs')->sole()->payload)->toContain($this->workspace->id);
});

function mockGraphClient(): MockInterface
{
    $client = Mockery::mock(GraphMailboxClient::class);
    app()->bind(GraphMailboxClient::class, fn () => $client);

    return $client;
}

test('the first Microsoft poll starts a delta query from now without importing old mail', function () {
    $mailbox = Mailbox::factory()->microsoft()->create(['default_desk_id' => Desk::sole()->id]);
    $client = mockGraphClient();
    $client->shouldReceive('start')->once()->andReturn('delta-1');
    $client->shouldNotReceive('page');

    PollMailbox::dispatchSync($mailbox);

    expect($mailbox->fresh())
        ->graph_delta_link->toBe('delta-1')
        ->polled_at->not->toBeNull();
});

test('new Microsoft mail is imported page by page into its own workspace only', function () {
    $other = Workspace::factory()->create();
    $other->execute(fn () => Mailbox::factory()->microsoft()->create(['graph_delta_link' => 'theirs']));
    $mailbox = Mailbox::factory()->microsoft()->create(['default_desk_id' => Desk::sole()->id, 'graph_delta_link' => 'delta-1']);
    $client = mockGraphClient();
    $client->shouldReceive('page')->with('delta-1')->andReturn(['ids' => ['msg-1'], 'next_link' => 'next-2', 'delta_link' => null]);
    $client->shouldReceive('page')->with('next-2')->andReturn(['ids' => ['msg-2'], 'next_link' => null, 'delta_link' => 'delta-2']);
    $client->shouldReceive('raw')->with('msg-1')->andReturn(rawFixtureEmail('html-only'));
    $client->shouldReceive('raw')->with('msg-2')->andReturn(rawFixtureEmail('outlook-reply'));

    PollMailbox::dispatchSync($mailbox);

    expect(Ticket::oldest('id')->pluck('subject')->all())->toBe(['Broken link', 'RE: Printer jam'])
        ->and(Ticket::pluck('mailbox_id')->unique()->all())->toBe([$mailbox->id])
        ->and(Ticket::withoutGlobalScopes()->where('workspace_id', $other->id)->exists())->toBeFalse()
        ->and($mailbox->fresh())->graph_delta_link->toBe('delta-2')->last_error->toBeNull();
});

test('a Microsoft message that fails to download fails the poll before the cursor passes it', function () {
    $mailbox = Mailbox::factory()->microsoft()->create(['default_desk_id' => Desk::sole()->id, 'graph_delta_link' => 'delta-1']);
    $client = mockGraphClient();
    $client->shouldReceive('page')->with('delta-1')->andReturn(['ids' => ['msg-1'], 'next_link' => 'next-2', 'delta_link' => null]);
    $client->shouldReceive('page')->with('next-2')->andReturn(['ids' => ['msg-2'], 'next_link' => null, 'delta_link' => 'delta-2']);
    $client->shouldReceive('raw')->with('msg-1')->andReturn(rawFixtureEmail('html-only'));
    $client->shouldReceive('raw')->with('msg-2')->andThrow(new RuntimeException('Timed out'));

    PollMailbox::dispatchSync($mailbox);

    expect($mailbox->fresh())
        ->graph_delta_link->toBe('next-2')
        ->last_error->toBe('Timed out')
        ->and(Ticket::count())->toBe(1);
});

test('an expired delta link restarts from now, and other Graph errors are saved', function (int $status, string $link, bool $hasError) {
    $mailbox = Mailbox::factory()->microsoft()->create(['default_desk_id' => Desk::sole()->id, 'graph_delta_link' => 'delta-1']);
    $client = mockGraphClient();
    $client->shouldReceive('page')->andThrow(new RequestException(new Response(new Psr7Response($status))));
    $client->shouldReceive('start')->andReturn('delta-fresh');

    PollMailbox::dispatchSync($mailbox);

    expect($mailbox->fresh())
        ->graph_delta_link->toBe($link)
        ->and($mailbox->fresh()->last_error !== null)->toBe($hasError);
})->with([
    'expired' => [410, 'delta-fresh', false],
    'server error' => [500, 'delta-1', true],
]);
