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
    Workspace::factory()->create(['subdomain' => 'acme'])
        ->users()->attach($this->user, ['role' => WorkspaceRole::Agent]);

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

function approveConsent(string $authorizeUrl, Client $client): string
{
    test()->get($authorizeUrl)->assertInertia(fn (AssertableInertia $page) => $page
        ->component('auth/OAuthAuthorize')
        ->where('client.name', $client->name)
        ->where('state', 'xyz')
        ->where('authToken', session('authToken')));

    return session('authToken');
}

test('guests are sent to login before consenting', function () {
    $this->get($this->authorizeUrl)->assertRedirect(route('login'));
});

test('an approved PKCE authorization yields tokens that call the API', function () {
    $this->actingAs($this->user);
    $authToken = approveConsent($this->authorizeUrl, $this->client);

    $redirect = $this->post('http://acme.harbor.test/oauth/authorize', [
        'state' => 'xyz',
        'client_id' => $this->client->id,
        'auth_token' => $authToken,
    ])->assertRedirect()->headers->get('Location');

    expect($redirect)->toStartWith('https://client.test/callback?');
    parse_str(parse_url($redirect, PHP_URL_QUERY), $query);
    expect($query['state'])->toBe('xyz');

    $tokens = $this->post('http://acme.harbor.test/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'redirect_uri' => 'https://client.test/callback',
        'code_verifier' => $this->verifier,
        'code' => $query['code'],
    ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token', 'expires_in'])->json();

    app('auth')->forgetGuards();

    $this->withToken($tokens['access_token'])
        ->getJson('http://acme.harbor.test/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id);
});

test('a denied authorization redirects back with an error', function () {
    $this->actingAs($this->user);
    $authToken = approveConsent($this->authorizeUrl, $this->client);

    $redirect = $this->delete('http://acme.harbor.test/oauth/authorize', [
        'state' => 'xyz',
        'client_id' => $this->client->id,
        'auth_token' => $authToken,
    ])->assertRedirect()->headers->get('Location');

    expect($redirect)->toStartWith('https://client.test/callback')->toContain('error=access_denied');
});
