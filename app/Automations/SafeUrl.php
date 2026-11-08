<?php

namespace App\Automations;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The guard in front of outbound automation requests (SSRF). A URL must be http(s) with a clean
 * hostname or IP literal and no credentials; its host is resolved (A and AAAA) and, unless private
 * targets are allowed, every address must be public. Anything unusual (decimal, octal or hex IPv4,
 * non-ASCII names, a numeric last label) is rejected instead of normalized.
 *
 * The vetted addresses come back so the request can be pinned to them ({@see self::curlOptions()}):
 * resolving again at connect time would let DNS rebinding swap in a private address.
 */
class SafeUrl
{
    public const int MAX_LENGTH = 2048;

    /** @var list<string> */
    protected const array BLOCKED_V4 = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
    ];

    /** @var list<string> */
    protected const array BLOCKED_V6 = [
        '::/96', '100::/64', '2001::/32', '2001:db8::/32', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    /** @var Closure(string): list<string> */
    protected Closure $resolver;

    /**
     * @param  (Closure(string): list<string>)|null  $resolver  Host to its A and AAAA addresses; tests swap it.
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? fn (string $host): array => $this->dns($host);
    }

    /**
     * Syntax only, no DNS.
     *
     * @return array{scheme: string, host: string, port: int, literal: bool}
     */
    public function parse(string $url): array
    {
        if (strlen($url) > self::MAX_LENGTH || preg_match('/[\x00-\x20\x7F\\\\]/', $url)) {
            throw new AutomationException(__('The URL is not valid.'));
        }

        if (! preg_match('#^(https?)://([^/?\#]*)#i', $url, $match)) {
            throw new AutomationException(__('The URL must start with http:// or https://.'));
        }

        $scheme = strtolower($match[1]);
        $authority = $match[2];

        if (str_contains($authority, '@')) {
            throw new AutomationException(__('The URL must not contain credentials.'));
        }

        if (preg_match('/^\[([0-9a-f:.]+)\](?::(\d{1,5}))?$/i', $authority, $bracketed)) {
            [$host, $portText, $literal] = [$bracketed[1], $bracketed[2] ?? '', true];
        } elseif (preg_match('/^([^:\[\]]+)(?::(\d{1,5}))?$/', $authority, $named)) {
            [$host, $portText, $literal] = [$named[1], $named[2] ?? '', false];
        } else {
            throw new AutomationException(__('The URL host is not valid.'));
        }

        $port = $portText !== '' ? (int) $portText : ($scheme === 'https' ? 443 : 80);

        if ($port < 1 || $port > 65535) {
            throw new AutomationException(__('The URL port is not valid.'));
        }

        $host = strtolower($host);

        if ($literal) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new AutomationException(__('The URL host is not valid.'));
            }
        } elseif (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // A name: ASCII labels only, and a last label that is not all digits (that would be a numeric IPv4 form).
            $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';

            if (! preg_match("/^(?:$label\\.)*$label\$/", $host) || strlen($host) > 253 || preg_match('/(^|\.)(\d+|0x[0-9a-f]*)$/', $host)) {
                throw new AutomationException(__('The URL host is not valid.'));
            }
        } else {
            $literal = true;
        }

        return ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'literal' => $literal];
    }

    /**
     * Parse, resolve and vet. Every resolved address must pass, so a name that also points inside is rejected.
     *
     * @return array{scheme: string, host: string, port: int, literal: bool, ips: list<string>}
     */
    public function check(string $url, bool $allowPrivate): array
    {
        $target = $this->parse($url);

        $addresses = $target['literal'] ? [$target['host']] : ($this->resolver)($target['host']);
        $ips = [];

        foreach ($addresses as $address) {
            $packed = @inet_pton($address);

            if ($packed === false) {
                continue;
            }

            if (! $allowPrivate && $this->isBlocked($packed)) {
                throw new AutomationException(__('The URL points to a private or reserved address.'));
            }

            $ips[] = inet_ntop($packed) ?: throw new AutomationException(__('The URL host could not be resolved.'));
        }

        if ($ips === []) {
            throw new AutomationException(__('The URL host could not be resolved.'));
        }

        return [...$target, 'ips' => array_values(array_unique($ips))];
    }

    /**
     * Guzzle options that make cURL connect to the vetted addresses whatever DNS says now. The
     * Host header, SNI and certificate check still use the name. Nothing to pin for IP literals.
     *
     * @param  array{host: string, port: int, literal: bool, ips: list<string>}  $target
     * @return array<string, mixed>
     */
    public function curlOptions(array $target): array
    {
        if ($target['literal']) {
            return [];
        }

        $ips = array_map(fn (string $ip): string => str_contains($ip, ':') ? "[$ip]" : $ip, $target['ips']);

        return ['curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:".implode(',', $ips)]]];
    }

    /**
     * Vet the URL and send the request, connected to the vetted addresses only. Redirects are not
     * followed and the timeout is 10 seconds to connect and in total.
     *
     * @param  array<string, string>  $headers
     *
     * @throws AutomationException When the URL is not allowed.
     * @throws ConnectionException
     */
    public function send(string $method, string $url, array $headers, ?string $body, bool $allowPrivate): Response
    {
        $options = $this->curlOptions($this->check($url, $allowPrivate));

        $request = Http::withHeaders($headers)
            ->timeout(10)->connectTimeout(10)->withoutRedirecting()
            ->withOptions($options);

        $contentTypes = array_filter(array_keys($headers), fn (string $name): bool => strtolower($name) === 'content-type');

        if ($body !== null) {
            $request = $request->withBody($body, $contentTypes === [] ? 'application/json' : $headers[reset($contentTypes)]);
        }

        return $request->send($method, $url);
    }

    /**
     * A validation rule that runs {@see self::check()}, for URLs that are saved to be called later.
     */
    public function rule(bool $allowPrivate): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($allowPrivate): void {
            try {
                $this->check(is_string($value) ? $value : '', $allowPrivate);
            } catch (AutomationException $exception) {
                $fail($exception->getMessage());
            }
        };
    }

    protected function isBlocked(string $packed): bool
    {
        if (strlen($packed) === 16) {
            // Addresses that embed an IPv4 one (mapped, NAT64, 6to4) are judged by it.
            $embedded = match (true) {
                str_starts_with($packed, str_repeat("\0", 10)."\xff\xff"), str_starts_with($packed, "\x00\x64\xff\x9b".str_repeat("\0", 8)) => substr($packed, 12),
                str_starts_with($packed, "\x20\x02") => substr($packed, 2, 4),
                default => null,
            };

            if ($embedded !== null) {
                return $this->isBlocked($embedded);
            }
        }

        $ranges = strlen($packed) === 4 ? self::BLOCKED_V4 : self::BLOCKED_V6;

        foreach ($ranges as $range) {
            [$base, $bits] = explode('/', $range);

            $packedBase = inet_pton($base);

            if ($packedBase !== false && $this->inRange($packed, $packedBase, (int) $bits)) {
                return true;
            }
        }

        return false;
    }

    protected function inRange(string $ip, string $base, int $bits): bool
    {
        $bytes = intdiv($bits, 8);

        if (strncmp($ip, $base, $bytes) !== 0) {
            return false;
        }

        $rest = $bits % 8;

        return $rest === 0 || (ord($ip[$bytes]) & (0xFF << (8 - $rest)) & 0xFF) === (ord($base[$bytes]) & (0xFF << (8 - $rest)) & 0xFF);
    }

    /**
     * @return list<string>
     */
    protected function dns(string $host): array
    {
        $records = @dns_get_record($host.'.', DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records)));
    }
}
