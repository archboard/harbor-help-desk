<?php

test('web responses carry the security headers', function () {
    $response = $this->get(route('login'));

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
});

test('hsts is only sent on secure requests', function () {
    $this->get(route('login'))->assertHeaderMissing('Strict-Transport-Security');

    $this->get(str_replace('http://', 'https://', route('login')))
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

test('x-forwarded-proto is honoured only from trusted proxies', function () {
    $headers = ['X-Forwarded-Proto' => 'https'];

    config(['trustedproxy.proxies' => null]);
    $this->get(route('login'), $headers)->assertHeaderMissing('Strict-Transport-Security');

    config(['trustedproxy.proxies' => '*']);
    $this->get(route('login'), $headers)->assertHeader('Strict-Transport-Security');
});
