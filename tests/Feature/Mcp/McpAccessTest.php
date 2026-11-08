<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->agent = User::factory()->create();
    $this->acme->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
});

/**
 * Send a JSON-RPC ping to the MCP endpoint on the given host.
 */
function mcpPing(string $host = 'acme'): TestResponse
{
    return test()->postJson("http://{$host}.harbor.test/mcp", ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);
}

test('an unauthenticated request is a 401 pointing at the workspace host metadata', function () {
    $response = mcpPing()->assertUnauthorized();

    expect($response->headers->get('WWW-Authenticate'))
        ->toBe('Bearer realm="mcp", resource_metadata="http://acme.harbor.test/.well-known/oauth-protected-resource/mcp"');
});

test('a token with the workspace scope can use the server', function () {
    Passport::actingAs($this->agent, [$this->acme->tokenScope()]);

    mcpPing()->assertOk()->assertJsonPath('id', 1);
});

test('a token without the workspace scope is forbidden', function () {
    Passport::actingAs($this->agent, ['mcp:use']);

    mcpPing()->assertForbidden();
});

test('a token for one workspace is forbidden on another host', function () {
    $this->other->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
    Passport::actingAs($this->agent, ['mcp:use', $this->acme->tokenScope()]);

    mcpPing('other')->assertForbidden();
});

test('a removed member is forbidden even with a valid scope', function () {
    Passport::actingAs($this->agent, [$this->acme->tokenScope()]);
    $this->acme->users()->detach($this->agent);

    mcpPing()->assertForbidden();
});

test('discovery metadata advertises the host it was requested on', function () {
    $this->getJson('http://acme.harbor.test/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertJsonPath('resource', 'http://acme.harbor.test/mcp')
        ->assertJsonPath('authorization_servers.0', 'http://acme.harbor.test');

    $this->getJson('http://other.harbor.test/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('issuer', 'http://other.harbor.test')
        ->assertJsonPath('authorization_endpoint', 'http://other.harbor.test/oauth/authorize')
        ->assertJsonPath('registration_endpoint', 'http://other.harbor.test/oauth/register');
});

test('dynamic client registration and discovery are throttled', function () {
    foreach (range(1, 10) as $_) {
        $this->getJson('http://acme.harbor.test/.well-known/oauth-authorization-server')->assertOk();
    }

    $this->getJson('http://acme.harbor.test/.well-known/oauth-authorization-server')->assertTooManyRequests();
});
