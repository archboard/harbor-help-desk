<?php

namespace App\Data\Automations\Actions;

use Illuminate\Validation\Rule;

class SendWebhookConfigData extends ActionConfigData
{
    public const array METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public const int MAX_HEADERS = 10;

    /**
     * @param  string  $url  http(s) only. Placeholders are filled in (URL-encoded) when it runs.
     * @param  list<array{name: string, value: string}>|null  $headers  Values may use placeholders.
     * @param  string|null  $body  JSON text. Placeholders are filled in as JSON-escaped text when it runs.
     */
    public function __construct(
        public string $method,
        public string $url,
        public ?array $headers = null,
        public ?string $body = null,
    ) {}

    public static function rules(): array
    {
        return [
            'method' => ['required', Rule::in(self::METHODS)],
            'url' => ['required', 'string', 'max:2048'],
            'headers' => ['nullable', 'array', 'max:'.self::MAX_HEADERS],
            'headers.*.name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'headers.*.value' => ['required', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
