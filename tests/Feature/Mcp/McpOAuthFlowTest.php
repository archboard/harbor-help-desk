<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

test('a client registers, is authorized on the workspace host and gets a token that works on /mcp', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    $acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $user = User::factory()->create();
    $acme->users()->attach($user, ['role' => WorkspaceRole::AGENT]);
    $other->users()->attach($user, ['role' => WorkspaceRole::AGENT]);

    $clientId = $this->postJson('http://acme.harbor.test/oauth/register', [
        'client_name' => 'Cursor',
        'redirect_uris' => ['cursor://anysphere.cursor-mcp/oauth/callback'],
    ])->assertCreated()->assertJsonPath('scope', 'mcp:use')->json('client_id');

    $verifier = Str::random(64);
    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'cursor://anysphere.cursor-mcp/oauth/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'xyz',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]);

    $this->actingAs($user)->get("http://acme.harbor.test/oauth/authorize?{$query}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/OAuthAuthorize')
            ->where('client.name', 'Cursor')
            ->where('scopes', ['Use MCP server'])
            ->has('workspaces', 2));

    $location = $this->post('http://acme.harbor.test/oauth/authorize', [
        'state' => 'xyz',
        'client_id' => $clientId,
        'auth_token' => session('authToken'),
        'workspaces' => [$acme->id],
    ])->assertRedirect()->headers->get('Location');

    parse_str(parse_url($location, PHP_URL_QUERY), $callback);

    $accessToken = $this->post('http://acme.harbor.test/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => 'cursor://anysphere.cursor-mcp/oauth/callback',
        'code_verifier' => $verifier,
        'code' => $callback['code'],
    ])->assertOk()->json('access_token');

    $ping = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'];

    Workspace::forgetCurrent();
    app('auth')->forgetGuards();
    $this->withToken($accessToken)->postJson('http://acme.harbor.test/mcp', $ping)->assertOk();

    Workspace::forgetCurrent();
    app('auth')->forgetGuards();
    $this->withToken($accessToken)->postJson('http://other.harbor.test/mcp', $ping)->assertForbidden();
});
