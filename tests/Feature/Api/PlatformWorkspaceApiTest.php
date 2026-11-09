<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['app.url' => 'http://harbor-help.test', 'harbor.domain' => 'harbor-help.test', 'harbor.multitenant' => true, 'harbor.platform_token' => 'platform-secret']);
});

/**
 * @return array<string, string>
 */
function platformHeaders(string $token = 'platform-secret'): array
{
    return ['Authorization' => "Bearer {$token}"];
}

test('the platform API needs the platform token', function (?string $token) {
    $headers = $token === null ? [] : platformHeaders($token);

    $this->postJson(route('api.platform.workspaces.store'), ['name' => 'Acme', 'subdomain' => 'acme'], $headers)
        ->assertUnauthorized();

    expect(Workspace::count())->toBe(0);
})->with([null, 'wrong']);

test('the platform API does not exist self-hosted or without a token', function (array $config) {
    config($config);

    $this->postJson(route('api.platform.workspaces.store'), ['name' => 'Acme', 'subdomain' => 'acme'], platformHeaders())
        ->assertNotFound();
})->with([
    'self-hosted' => [['harbor.multitenant' => false]],
    'no token' => [['harbor.platform_token' => null]],
]);

test('a workspace API token cannot reach the platform API', function () {
    ['workspace' => $workspace, 'agent' => $agent] = acmeWithAgent();
    actAs($agent, $workspace);

    $this->deleteJson(route('api.platform.workspaces.destroy', $workspace))->assertUnauthorized();

    expect($workspace->exists())->toBeTrue();
});

test('creating a workspace returns a setup link on its host', function () {
    $response = $this->postJson(route('api.platform.workspaces.store'), ['name' => 'Acme', 'subdomain' => 'acme'], platformHeaders())
        ->assertCreated()
        ->assertJson(['name' => 'Acme', 'subdomain' => 'acme', 'url' => 'http://acme.harbor-help.test/', 'has_owner' => false]);

    expect($response->json('setup_url'))->toStartWith('http://acme.harbor-help.test/setup?')
        ->and(Workspace::sole()->id)->toBe($response->json('id'));
});

test('invalid input is reported as JSON', function () {
    $this->postJson(route('api.platform.workspaces.store'), ['subdomain' => 'www'], platformHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'subdomain']);
});

test('updating reissues the setup link until the workspace has an owner', function () {
    $workspace = Workspace::factory()->create(['subdomain' => 'acme']);

    $this->patchJson(route('api.platform.workspaces.update', $workspace), ['subdomain' => 'acme-co'], platformHeaders())
        ->assertOk()
        ->assertJsonPath('subdomain', 'acme-co')
        ->assertJsonPath('setup_url', fn (string $url): bool => str_starts_with($url, 'http://acme-co.harbor-help.test/setup?'));

    $workspace->users()->attach(User::factory()->create(), ['role' => WorkspaceRole::OWNER]);

    $this->patchJson(route('api.platform.workspaces.update', $workspace), [], platformHeaders())
        ->assertOk()
        ->assertJson(['has_owner' => true, 'setup_url' => null]);
});

test('deleting a workspace removes it', function () {
    $workspace = Workspace::factory()->create();

    $this->deleteJson(route('api.platform.workspaces.destroy', $workspace), [], platformHeaders())->assertNoContent();

    expect(Workspace::count())->toBe(0);
});
