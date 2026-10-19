<?php

use App\Actions\ApiTokens\CreatePersonalAccessToken;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Token;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace->users()->attach($this->user, ['role' => WorkspaceRole::AGENT]);
});

test('api tokens page requires password confirmation', function () {
    $this->actingAs($this->user)
        ->get(route('api-tokens.edit'))
        ->assertRedirect(route('password.confirm'));
});

test('api tokens page lists only active personal access tokens', function () {
    $token = $this->user->createToken('Active', [$this->workspace->tokenScope()])->token;
    $this->user->createToken('Revoked')->token->revoke();

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('api-tokens.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiTokens')
            ->where('tokens', [[
                'id' => $token->id,
                'name' => 'Active',
                'workspaces' => [$this->workspace->name],
                'created_at' => $token->created_at->toIso8601String(),
                'expires_at' => $token->expires_at->toIso8601String(),
            ]])
            ->where('workspaces', [[
                'id' => $this->workspace->id,
                'name' => $this->workspace->name,
                'subdomain' => $this->workspace->subdomain,
                'is_admin' => false,
            ]]),
        );
});

test('a created token is flashed once and authenticates against the api', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('api-tokens.store'), ['name' => 'CLI', 'workspaces' => [$this->workspace->id]])
        ->assertRedirect()
        ->assertInertiaFlash('apiToken');

    $plainTextToken = session('inertia.flash_data')['apiToken'];

    app('auth')->forgetGuards();

    $this->withToken($plainTextToken)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('id', $this->user->id);
});

test('creating a token requires a workspace the user belongs to', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('api-tokens.store'), ['name' => 'CLI', 'workspaces' => [Workspace::factory()->create()->id]])
        ->assertSessionHasErrors('workspaces.0');

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('api-tokens.store'), ['name' => 'CLI'])
        ->assertSessionHasErrors('workspaces');
});

test('workspaces the user does not belong to are dropped from new tokens', function () {
    $stranger = Workspace::factory()->create();

    $result = app(CreatePersonalAccessToken::class)
        ->handle($this->user, 'CLI', [$this->workspace->id, $stranger->id]);

    expect($result->token->scopes)->toBe([$this->workspace->tokenScope()]);
});

test('a revoked token no longer authenticates', function () {
    $result = $this->user->createToken('CLI', [$this->workspace->tokenScope()]);

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('api-tokens.destroy', $result->token->id))
        ->assertRedirect();

    app('auth')->forgetGuards();

    $this->withToken($result->accessToken)->getJson('/api/v1/user')->assertUnauthorized();
});

test('users cannot revoke another user\'s token', function () {
    $token = User::factory()->create()->createToken('Theirs')->token;

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('api-tokens.destroy', $token->id))
        ->assertNotFound();

    expect(Token::find($token->id)->revoked)->toBeFalse();
});
