<?php

use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test', 'harbor.platform_token' => 'platform-secret']);
});

test('the api is throttled per token', function () {
    ['workspace' => $workspace, 'agent' => $agent] = acmeWithAgent();
    actAs($agent, $workspace);

    foreach (range(1, 120) as $ignored) {
        $this->getJson(route('api.v1.user'))->assertOk();
    }

    $this->getJson(route('api.v1.user'))->assertTooManyRequests();
});

test('password reset requests are throttled per IP and email', function () {
    $ip = ['REMOTE_ADDR' => '203.0.113.10'];

    foreach (range(1, 5) as $ignored) {
        $this->withServerVariables($ip)->post(route('password.email'), ['email' => 'a@example.com'])->assertStatus(302);
    }

    $this->withServerVariables($ip)->post(route('password.email'), ['email' => 'a@example.com'])->assertTooManyRequests();
    $this->withServerVariables($ip)->post(route('password.email'), ['email' => 'b@example.com'])->assertStatus(302);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])->post(route('password.email'), ['email' => 'a@example.com'])->assertStatus(302);
});

test('the webhook limiter is keyed per IP', function () {
    $limiter = RateLimiter::limiter('webhooks');
    $limit = fn (string $ip) => $limiter(request()->duplicate(server: ['REMOTE_ADDR' => $ip]));

    expect($limit('203.0.113.1')->key)->toBe('203.0.113.1')
        ->and($limit('203.0.113.2')->key)->toBe('203.0.113.2')
        ->and($limit('203.0.113.1')->maxAttempts)->toBe(600);
});

test('the platform API is throttled', function () {
    $headers = ['Authorization' => 'Bearer wrong'];

    foreach (range(1, 60) as $ignored) {
        $this->postJson(route('api.platform.workspaces.store'), [], $headers)->assertUnauthorized();
    }

    $this->postJson(route('api.platform.workspaces.store'), [], $headers)->assertTooManyRequests();
});
