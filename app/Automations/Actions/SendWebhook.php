<?php

namespace App\Automations\Actions;

use App\Automations\AutomationException;
use App\Automations\Context;
use App\Automations\SafeUrl;
use App\Data\Automations\Actions\SendWebhookConfigData;
use App\Jobs\SendAutomationWebhook;
use App\Models\Desk;
use App\Models\User;
use Closure;

/**
 * Queues an HTTP request ({@see SendAutomationWebhook}) with a templated URL, headers and JSON body.
 * Placeholder values are escaped for where they land: URL-encoded in the URL, JSON-escaped in the
 * body (so they go inside a JSON string, or stand alone as a number), stripped of control
 * characters in headers. The URL is vetted again, with DNS, when the job runs.
 */
class SendWebhook extends ConfiguredAction
{
    public const int MAX_BODY_BYTES = 100_000;

    /** Headers the client sets itself, or that change how the connection works. */
    protected const array FORBIDDEN_HEADERS = [
        'host', 'content-length', 'connection', 'keep-alive', 'transfer-encoding', 'te', 'trailer', 'upgrade',
        'proxy-authorization', 'proxy-authenticate', 'proxy-connection', 'expect',
    ];

    public function __construct(protected SafeUrl $safeUrl) {}

    public function key(): string
    {
        return 'send_webhook';
    }

    public function label(): string
    {
        return __('Send a webhook');
    }

    public function configData(): string
    {
        return SendWebhookConfigData::class;
    }

    public function contextRules(?User $editor, ?Desk $scope): array
    {
        return [
            'url' => [function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    // A URL with placeholders can only be fully checked when it runs.
                    str_contains((string) $value, '{{')
                        ? $this->safeUrl->parse(preg_replace('/\{\{[^}]*\}\}/', 'x', (string) $value))
                        : $this->safeUrl->parse((string) $value);
                } catch (AutomationException $exception) {
                    $fail($exception->getMessage());
                }
            }],
            'headers.*.name' => [function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && in_array(strtolower($value), self::FORBIDDEN_HEADERS, true)) {
                    $fail(__('This header can not be set.'));
                }
            }],
            'body' => [function (string $attribute, mixed $value, Closure $fail): void {
                // Every placeholder stands for a number here, which is valid inside a string or alone.
                if (is_string($value) && ! json_validate(preg_replace('/\{\{[^}]*\}\}/', '0', $value))) {
                    $fail(__('The body must be valid JSON.'));
                }
            }],
        ];
    }

    public function execute(Context $context, array $config): string
    {
        $config = SendWebhookConfigData::from($config);
        $url = $this->url($context, $config);
        $target = $this->safeUrl->parse($url);

        $headers = [];

        foreach ($config->headers ?? [] as $header) {
            $name = (string) $header['name'];

            if (in_array(strtolower($name), self::FORBIDDEN_HEADERS, true)) {
                throw new AutomationException(__('The header “:name” can not be set.', ['name' => $name]));
            }

            $headers[$name] = $this->render($context, (string) $header['value'], 1000, fn (string $value): string => preg_replace('/[\x00-\x1F\x7F]/', '', $value));
        }

        $body = $this->body($context, $config);
        $run = $context->run;
        $stepId = $context->stepId;

        $context->defer(function () use ($run, $stepId, $config, $url, $headers, $body): void {
            SendAutomationWebhook::dispatch($run, $stepId, $config->method, $url, $headers, $body);
        });

        return __('Queued :method to :host', ['method' => $config->method, 'host' => $target['host']]);
    }

    public function describe(Context $context, array $config): string
    {
        $config = SendWebhookConfigData::from($config);

        return __('Would send :method to :url', ['method' => $config->method, 'url' => $this->url($context, $config)]);
    }

    protected function url(Context $context, SendWebhookConfigData $config): string
    {
        return $this->render($context, $config->url, SafeUrl::MAX_LENGTH, rawurlencode(...));
    }

    protected function body(Context $context, SendWebhookConfigData $config): ?string
    {
        if ($config->body === null || trim($config->body) === '') {
            return null;
        }

        $body = $this->render($context, $config->body, PHP_INT_MAX, fn (string $value): string => substr(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR), 1, -1));

        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new AutomationException(__('The webhook body is too large.'));
        }

        if (! json_validate($body)) {
            throw new AutomationException(__('The webhook body is not valid JSON once its placeholders are filled in.'));
        }

        return $body;
    }
}
