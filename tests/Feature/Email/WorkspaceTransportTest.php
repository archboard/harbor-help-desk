<?php

use App\Enums\MailboxDriver;
use App\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\MagicLinkNotification;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;

/**
 * Send a magic link to the user for real (no fakes), through the default mailer.
 */
function sendMagicLinkTo(User $user): void
{
    $user->notify(new MagicLinkNotification('https://acme.harbor.test/magic'));
}

/**
 * Replace the SMTP transport with an in-memory one, so a workspace mailbox's mail can be read back.
 */
function captureMailboxMail(): ArrayTransport
{
    $transport = new ArrayTransport;
    Mail::extend('smtp', fn (): ArrayTransport => $transport);

    return $transport;
}

function hostedMailbox(): Mailbox
{
    return Mailbox::factory()->create([
        'driver' => MailboxDriver::HOSTED,
        'name' => 'Acme Support',
        'address' => 'acme@in.harbor.test',
        'inbound_address' => 'acme@in.harbor.test',
    ]);
}

function platformMail(): ArrayTransport
{
    return Mail::mailer('array')->getSymfonyTransport();
}

function fromOf(SentMessage $message): string
{
    $from = $message->getOriginalMessage()->getFrom()[0];

    return "{$from->getName()} <{$from->getAddress()}>";
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
});

test('a magic link goes out through the mailbox chosen in the workspace settings', function () {
    $smtp = captureMailboxMail();
    Mailbox::factory()->create(['address' => 'oldest@acme.test']);
    $chosen = Mailbox::factory()->create(['address' => 'chosen@acme.test', 'name' => 'Acme Care']);
    $this->workspace->update(['settings' => ['mailNotificationMailboxId' => $chosen->id]]);

    sendMagicLinkTo($this->user);

    expect($smtp->messages())->toHaveCount(1)
        ->and(fromOf($smtp->messages()->first()))->toBe('Acme Care <chosen@acme.test>')
        ->and($smtp->messages()->first()->getEnvelope()->getSender()->getAddress())->toBe('chosen@acme.test')
        ->and(platformMail()->messages())->toBeEmpty();
});

test('it falls back to the oldest mailbox when none is chosen or the chosen one is gone', function (?string $chosenId) {
    $smtp = captureMailboxMail();
    Mailbox::factory()->create(['address' => 'oldest@acme.test', 'name' => 'Oldest']);
    Mailbox::factory()->create(['address' => 'newer@acme.test']);
    $this->workspace->update(['settings' => ['mailNotificationMailboxId' => $chosenId]]);

    sendMagicLinkTo($this->user);

    expect(fromOf($smtp->messages()->sole()))->toBe('Oldest <oldest@acme.test>');
})->with([
    'none chosen' => [null],
    'chosen mailbox deleted' => ['mbx_missing'],
]);

test('it uses the platform mailer when the workspace has no mailbox', function () {
    Mailbox::query()->delete();

    sendMagicLinkTo($this->user);

    expect(platformMail()->messages())->toHaveCount(1)
        ->and(fromOf(platformMail()->messages()->first()))->toBe(config('mail.from.name').' <'.config('mail.from.address').'>');
});

test('the hosted mailbox sends through the platform mailer without looping back', function () {
    $hosted = hostedMailbox();

    $hosted->mailer()->raw('Hello', fn ($message) => $message->to('jane@example.com')->subject('Hi'));

    expect(platformMail()->messages())->toHaveCount(1);
});

test('app email sent from a hosted notification mailbox goes From its inbound address', function () {
    hostedMailbox();

    sendMagicLinkTo($this->user);

    expect(fromOf(platformMail()->messages()->sole()))->toBe('Acme Support <acme@in.harbor.test>');
});

test('in multitenant mode, app email outside a workspace uses the platform mailer', function () {
    config(['harbor.multitenant' => true]);
    Workspace::forgetCurrent();

    sendMagicLinkTo($this->user);

    expect(platformMail()->messages())->toHaveCount(1)
        ->and(fromOf(platformMail()->messages()->first()))->toBe(config('mail.from.name').' <'.config('mail.from.address').'>');
});

test('self-hosted, app email outside a request resolves the single workspace', function () {
    $smtp = captureMailboxMail();
    Mailbox::factory()->create(['address' => 'oldest@acme.test', 'name' => 'Oldest']);
    config(['harbor.multitenant' => false]);
    Workspace::forgetCurrent();

    sendMagicLinkTo($this->user);

    expect(fromOf($smtp->messages()->sole()))->toBe('Oldest <oldest@acme.test>');
});

test('the platform mailer cannot be the workspace mailer', function () {
    config(['harbor.mail.platform_mailer' => 'workspace']);
    Mail::purge('workspace');

    Mail::mailer('workspace');
})->throws(InvalidArgumentException::class);

/**
 * The MIME that a Graph `sendMail` request carried.
 */
function graphMime(Request $request): string
{
    return (string) base64_decode($request->body());
}

test('a Microsoft 365 mailbox sends through Graph as base64 MIME, without SMTP', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response(status: 202)]);
    $smtp = captureMailboxMail();
    $microsoft = Mailbox::factory()->microsoft()->create(['address' => 'help@acme.test', 'name' => 'Acme Support']);

    $microsoft->mailer()->raw('Hello', fn ($message) => $message->from($microsoft->fromAddress()->address)->to('jane@example.com')->subject('Hi'));

    Http::assertSent(fn ($request) => $request->url() === 'https://graph.microsoft.com/v1.0/me/sendMail'
        && $request->hasHeader('Authorization', 'Bearer access-1')
        && $request->hasHeader('Content-Type', 'text/plain')
        && str_contains(graphMime($request), 'Subject: Hi')
        && str_contains(graphMime($request), 'To: jane@example.com'));
    expect($smtp->messages())->toBeEmpty();
});

test('a Graph error fails the send', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorAccessDenied']], 403)]);
    $microsoft = Mailbox::factory()->microsoft()->create();

    $microsoft->mailer()->raw('Hello', fn ($message) => $message->from($microsoft->address)->to('jane@example.com'));
})->throws(RequestException::class);

test('app email goes out through the current workspace Microsoft mailbox and its token only', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response(status: 202)]);
    Workspace::factory()->create()->execute(fn () => Mailbox::factory()->microsoft()->create([
        'settings' => ['access_token' => 'other-token', 'refresh_token' => 'x', 'expires_at' => now()->addHour()->getTimestamp()],
    ]));
    Mailbox::factory()->microsoft()->create(['address' => 'help@acme.test', 'name' => 'Acme Support']);

    sendMagicLinkTo($this->user);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer access-1')
        && str_contains(graphMime($request), 'From: Acme Support <help@acme.test>'));
});
