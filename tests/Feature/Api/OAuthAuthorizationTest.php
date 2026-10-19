<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Passport\Client;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->user = User::factory()->create();
    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->acme->users()->attach($this->user, ['role' => WorkspaceRole::AGENT]);
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->other->users()->attach($this->user, ['role' => WorkspaceRole::AGENT]);

    $this->client = Client::factory()->asPublic()->create([
        'redirect_uris' => ['https://client.test/callback'],
    ]);

    $this->verifier = Str::random(64);
    $this->authorizeUrl = 'http://acme.harbor.test/oauth/authorize?'.http_build_query([
        'client_id' => $this->client->id,
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'state' => 'xyz',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $this->verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]);
});

function showConsent(string $authorizeUrl, Client $client): string
{
    test()->get($authorizeUrl)->assertInertia(fn (AssertableInertia $page) => $page
        ->component('auth/OAuthAuthorize')
        ->where('client.name', $client->name)
        ->has('workspaces', 2)
        ->where('state', 'xyz')
        ->where('authToken', session('authToken')));

    return session('authToken');
}

/**
 * Approve the consent screen for the given workspaces and exchange the code for tokens.
 *
 * @param  list<string>  $workspaceIds
 * @return array{access_token: string, refresh_token: string}
 */
function issueTokens(string $authorizeUrl, Client $client, string $verifier, array $workspaceIds): array
{
    $redirect = test()->post('http://acme.harbor.test/oauth/authorize', [
        'state' => 'xyz',
        'client_id' => $client->id,
        'auth_token' => showConsent($authorizeUrl, $client),
        'workspaces' => $workspaceIds,
    ])->assertRedirect()->headers->get('Location');

    expect($redirect)->toStartWith('https://client.test/callback?');
    parse_str(parse_url($redirect, PHP_URL_QUERY), $query);
    expect($query['state'])->toBe('xyz');

    $tokens = test()->post('http://acme.harbor.test/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.test/callback',
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token', 'expires_in'])->json();

    forgetRequestState();

    return $tokens;
}

/**
 * Reset the tenant and auth guards that the previous request left behind.
 */
function forgetRequestState(): void
{
    Workspace::forgetCurrent();
    app('auth')->forgetGuards();
}

test('guests are sent to login before consenting', function () {
    $this->get($this->authorizeUrl)->assertRedirect(route('login'));
});

test('an approved PKCE authorization yields a token for the picked workspaces only', function () {
    $this->actingAs($this->user);
    $tokens = issueTokens($this->authorizeUrl, $this->client, $this->verifier, [$this->acme->id]);

    $this->withToken($tokens['access_token'])
        ->getJson('http://acme.harbor.test/api/v1/user')
        ->assertOk()
        ->assertExactJson(['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email]);

    forgetRequestState();

    $this->withToken($tokens['access_token'])
        ->getJson('http://other.harbor.test/api/v1/user')
        ->assertForbidden();
});

test('a refreshed token keeps its workspaces', function () {
    $this->actingAs($this->user);
    $tokens = issueTokens($this->authorizeUrl, $this->client, $this->verifier, [$this->acme->id]);

    $refreshed = $this->post('http://acme.harbor.test/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $this->client->id,
        'refresh_token' => $tokens['refresh_token'],
    ])->assertOk()->json();

    forgetRequestState();

    $this->withToken($refreshed['access_token'])->getJson('http://acme.harbor.test/api/v1/user')->assertOk();
    forgetRequestState();
    $this->withToken($refreshed['access_token'])->getJson('http://other.harbor.test/api/v1/user')->assertForbidden();
});

test('the consent screen is shown again after a previous approval', function () {
    $this->actingAs($this->user);
    issueTokens($this->authorizeUrl, $this->client, $this->verifier, [$this->acme->id]);

    showConsent($this->authorizeUrl, $this->client);
});

test('approval requires at least one workspace', function () {
    $this->actingAs($this->user);

    $this->post('http://acme.harbor.test/oauth/authorize', [
        'state' => 'xyz',
        'client_id' => $this->client->id,
        'auth_token' => showConsent($this->authorizeUrl, $this->client),
    ])->assertSessionHasErrors('workspaces');
});

test('users cannot grant a workspace they do not belong to', function () {
    $this->actingAs($this->user);
    $stranger = Workspace::factory()->create();

    $this->post('http://acme.harbor.test/oauth/authorize', [
        'state' => 'xyz',
        'client_id' => $this->client->id,
        'auth_token' => showConsent($this->authorizeUrl, $this->client),
        'workspaces' => [$stranger->id],
    ])->assertSessionHasErrors('workspaces.0');
});

test('a denied authorization redirects back with an error', function () {
    $this->actingAs($this->user);

    $redirect = $this->delete('http://acme.harbor.test/oauth/authorize', [
        'state' => 'xyz',
        'client_id' => $this->client->id,
        'auth_token' => showConsent($this->authorizeUrl, $this->client),
    ])->assertRedirect()->headers->get('Location');

    expect($redirect)->toStartWith('https://client.test/callback')->toContain('error=access_denied');
});
