<?php

namespace App\Data\Realtime;

use Spatie\LaravelData\Data;

/**
 * The public connection settings the browser needs for Reverb. Never includes the app secret.
 */
class RealtimeData extends Data
{
    public function __construct(
        public string $key,
        public string $host,
        public int $port,
        public string $scheme,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            key: (string) config('broadcasting.connections.reverb.key'),
            host: (string) config('broadcasting.connections.reverb.options.host'),
            port: (int) config('broadcasting.connections.reverb.options.port'),
            scheme: (string) config('broadcasting.connections.reverb.options.scheme'),
        );
    }
}
