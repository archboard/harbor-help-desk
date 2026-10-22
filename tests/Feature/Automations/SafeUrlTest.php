<?php

use App\Automations\AutomationException;
use App\Automations\SafeUrl;

/**
 * @param  array<string, list<string>>  $dns
 */
function safeUrl(array $dns = []): SafeUrl
{
    return new SafeUrl(fn (string $host): array => $dns[$host] ?? []);
}

dataset('private urls', [
    'loopback' => 'http://127.0.0.1/',
    'private 10/8' => 'http://10.0.0.5/hook',
    'private 192.168' => 'http://192.168.1.1:8080/',
    'private 172.16' => 'https://172.31.0.1/',
    'metadata' => 'http://169.254.169.254/latest/meta-data',
    'cgnat' => 'http://100.64.0.1/',
    'unspecified' => 'http://0.0.0.0/',
    'multicast' => 'http://224.0.0.1/',
    'ipv6 loopback' => 'http://[::1]/',
    'ipv6 unique local' => 'http://[fd12::1]/',
    'ipv6 link local' => 'http://[fe80::1]/',
    'mapped loopback' => 'http://[::ffff:127.0.0.1]/',
    'mapped private' => 'http://[::ffff:10.0.0.1]/',
    'dns to private' => 'http://internal.example.com/',
    'dns to loopback ipv6' => 'http://v6.example.com/',
    'dns mixed with a public address' => 'http://mixed.example.com/',
]);

dataset('malformed urls', [
    'decimal ip' => 'http://2130706433/',
    'hex ip' => 'http://0x7f000001/',
    'dotted hex ip' => 'http://0x7f.0.0.1/',
    'octal ip' => 'http://017700000001/',
    'short ip' => 'http://127.1/',
    'leading zeros' => 'http://010.0.0.1/',
    'ftp' => 'ftp://example.com/',
    'file' => 'file:///etc/passwd',
    'no scheme' => 'example.com/hook',
    'userinfo' => 'http://user:pass@example.com/',
    'userinfo trick' => 'http://example.com@127.0.0.1/',
    'whitespace' => 'http://exa mple.com/',
    'backslash' => 'http://example.com\\@127.0.0.1/',
    'unicode host' => 'http://exämple.com/',
    'bad port' => 'http://example.com:99999/',
]);

$dns = [
    'internal.example.com' => ['10.1.2.3'],
    'v6.example.com' => ['::1'],
    'mixed.example.com' => ['93.184.216.34', '192.168.0.9'],
    'example.com' => ['93.184.216.34'],
    'hooks.example.com' => ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946'],
];

test('private, loopback, link-local and reserved targets are blocked', function (string $url) use ($dns) {
    expect(fn () => safeUrl($dns)->check($url, false))->toThrow(AutomationException::class);
})->with('private urls');

test('the same targets pass when private urls are allowed', function (string $url) use ($dns) {
    expect(safeUrl($dns)->check($url, true)['ips'])->not->toBeEmpty();
})->with('private urls');

test('malformed hosts, other schemes and credentials are always rejected', function (string $url) use ($dns) {
    expect(fn () => safeUrl($dns)->check($url, true))->toThrow(AutomationException::class);
})->with('malformed urls');

test('public addresses and names pass and the vetted ips are pinned for the connection', function () use ($dns) {
    expect(safeUrl($dns)->check('http://8.8.8.8/x', false)['ips'])->toBe(['8.8.8.8']);

    $target = safeUrl($dns)->check('https://hooks.example.com:8443/x?a=1', false);

    expect($target['ips'])->toBe(['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946'])
        ->and(safeUrl()->curlOptions($target))->toBe(['curl' => [CURLOPT_RESOLVE => ['hooks.example.com:8443:93.184.216.34,[2606:2800:220:1:248:1893:25c8:1946]']]])
        ->and(safeUrl()->curlOptions(safeUrl($dns)->check('http://8.8.8.8/', false)))->toBe([]);
});

test('a name that does not resolve is rejected', function () {
    expect(fn () => safeUrl()->check('https://nothing.example.com/', false))->toThrow(AutomationException::class);
});
