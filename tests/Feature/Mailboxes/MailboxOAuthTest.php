<?php

use App\Actions\Mailboxes\RefreshMailboxToken;
use App\Enums\MailboxDriver;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'harbor.multitenant' => true,
        'harbor.domain' => 'harbor.test',
        'app.url' => 'http://harbor.test',
        'services.google_mail' => ['client_id' => 'google-client', 'client_secret' => 'google-secret'],
    ]);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme', 'name' => 'Acme']);
    $this->admin = User::factory()->create();
    $this->acme->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
});

/**
 * Start the Google consent from acme and return the `state` it carries.
 */
function startGoogleConsent(User $user): string
{
    $location = test()->actingAs($user)->get('http://acme.harbor.test/mailboxes/oauth/google')->assertRedirect()->headers->get('Location');

    expect($location)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect($query['redirect_uri'])->toBe('http://harbor.test/mailboxes/oauth/callback');

    return $query['state'];
}

function fakeGoogleTokens(): void
{
    $idToken = 'header.'.rtrim(strtr(base64_encode(json_encode(['email' => 'help@acme.com'])), '+/', '-_'), '=').'.signature';

    Http::fake(['oauth2.googleapis.com/token' => Http::response([
        'access_token' => 'access-1',
        'refresh_token' => 'refresh-1',
        'expires_in' => 3600,
        'id_token' => $idToken,
    ])]);
}

test('connecting Google creates the mailbox in the workspace that started it', function () {
    fakeGoogleTokens();
    $state = startGoogleConsent($this->admin);

    $this->get('http://harbor.test/mailboxes/oauth/callback?code=abc&state='.urlencode($state))
        ->assertRedirect('http://acme.harbor.test/workspace/email');

    $mailbox = Mailbox::withoutGlobalScopes()->sole();
    expect($mailbox->workspace_id)->toBe($this->acme->id)
        ->and($mailbox->driver)->toBe(MailboxDriver::GOOGLE)
        ->and($mailbox->address)->toBe('help@acme.com')
        ->and($mailbox->default_desk_id)->toBe($this->acme->execute(fn () => Desk::sole()->id))
        ->and($mailbox->settings['refresh_token'])->toBe('refresh-1');
    Http::assertSent(fn ($request) => $request['code'] === 'abc' && $request['redirect_uri'] === 'http://harbor.test/mailboxes/oauth/callback');
});

test('a forged or expired state is rejected', function () {
    fakeGoogleTokens();
    $state = startGoogleConsent($this->admin);

    $this->get('http://harbor.test/mailboxes/oauth/callback?code=abc&state=forged')->assertForbidden();

    $this->travel(11)->minutes();
    $this->get('http://harbor.test/mailboxes/oauth/callback?code=abc&state='.urlencode($state))->assertForbidden();

    expect(Mailbox::withoutGlobalScopes()->count())->toBe(0);
    Http::assertNothingSent();
});

test('only admins can connect a mailbox', function () {
    $agent = User::factory()->create();
    $this->acme->users()->attach($agent, ['role' => WorkspaceRole::AGENT]);

    $this->actingAs($agent)->get('http://acme.harbor.test/mailboxes/oauth/google')->assertForbidden();
});

test('an expiring access token is refreshed before connecting', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-2', 'expires_in' => 3600])]);
    $mailbox = $this->acme->execute(fn () => Mailbox::factory()->create([
        'driver' => MailboxDriver::GOOGLE,
        'address' => 'help@acme.com',
        'settings' => ['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_at' => now()->addMinutes(2)->getTimestamp()],
    ]));

    expect($mailbox->connection('imap'))->toMatchArray(['host' => 'imap.gmail.com', 'username' => 'help@acme.com', 'password' => 'access-2'])
        ->and($mailbox->fresh()->settings)->toMatchArray(['access_token' => 'access-2', 'refresh_token' => 'refresh-1']);
    Http::assertSent(fn ($request) => $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'refresh-1' && ! isset($request['scope']));

    // Now fresh for an hour, so the next connection reuses it.
    expect($mailbox->connection('smtp')['password'])->toBe('access-2');
    Http::assertSentCount(1);
});

test('reconnecting starts on the mailbox account', function () {
    $location = $this->actingAs($this->admin)->get('http://acme.harbor.test/mailboxes/oauth/google?login_hint=help%40acme.com')->headers->get('Location');

    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
    expect($query['login_hint'])->toBe('help@acme.com');
});

test('connecting an address the workspace already has reconnects that mailbox', function () {
    $mailbox = $this->acme->execute(fn () => Mailbox::factory()->create([
        'driver' => MailboxDriver::GOOGLE,
        'address' => 'Help@Acme.com',
        'settings' => ['access_token' => 'revoked', 'refresh_token' => 'revoked', 'expires_at' => 0],
        'last_error' => 'invalid_grant',
    ]));
    fakeGoogleTokens();

    $this->get('http://harbor.test/mailboxes/oauth/callback?code=abc&state='.urlencode(startGoogleConsent($this->admin)))
        ->assertRedirect('http://acme.harbor.test/workspace/email');

    expect(Mailbox::withoutGlobalScopes()->sole()->is($mailbox))->toBeTrue()
        ->and($mailbox->fresh())
        ->last_error->toBeNull()
        ->settings->toMatchArray(['access_token' => 'access-1', 'refresh_token' => 'refresh-1']);
});

test('connecting an address another workspace has creates a new mailbox and leaves theirs alone', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $foreign = $other->execute(fn () => Mailbox::factory()->create([
        'driver' => MailboxDriver::GOOGLE,
        'address' => 'help@acme.com',
        'settings' => ['access_token' => 'theirs', 'refresh_token' => 'theirs', 'expires_at' => 0],
    ]));
    fakeGoogleTokens();

    $this->get('http://harbor.test/mailboxes/oauth/callback?code=abc&state='.urlencode(startGoogleConsent($this->admin)));

    expect($foreign->fresh()->settings['refresh_token'])->toBe('theirs')
        ->and(Mailbox::withoutGlobalScopes()->where('workspace_id', $this->acme->id)->sole()->settings['refresh_token'])->toBe('refresh-1');
});

test('Microsoft token refreshes ask for the Graph scopes again', function () {
    Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'access-2', 'expires_in' => 3600])]);
    $mailbox = $this->acme->execute(fn () => Mailbox::factory()->microsoft()->create(['settings' => ['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_at' => 0]]));

    expect(app(RefreshMailboxToken::class)->handle($mailbox))->toBe('access-2');
    Http::assertSent(fn ($request) => str_contains($request['scope'], 'https://graph.microsoft.com/Mail.Send'));
});
