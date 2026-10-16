<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Token;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace->users()->attach($this->user, ['role' => WorkspaceRole::Agent]);
});

test('api tokens page requires password confirmation', function () {
    $this->actingAs($this->user)
        ->get(route('api-tokens.edit'))
        ->assertRedirect(route('password.confirm'));
});

test('api tokens page lists only active personal access tokens', function () {
    $this->user->createToken('Active');
    $this->user->createToken('Revoked')->token->revoke();

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('api-tokens.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiTokens')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'Active'),
        );
});

test('a created token is flashed once and authenticates against the api', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('api-tokens.store'), ['name' => 'CLI'])
        ->assertRedirect()
        ->assertInertiaFlash('apiToken');

    $plainTextToken = session('inertia.flash_data')['apiToken'];

    app('auth')->forgetGuards();

    $this->withToken($plainTextToken)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id);
});

test('a revoked token no longer authenticates', function () {
    $result = $this->user->createToken('CLI');

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
