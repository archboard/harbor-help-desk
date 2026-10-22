<?php

namespace App\Jobs;

use App\Automations\AutomationException;
use App\Automations\NotifyCreatorOfFailure;
use App\Automations\SafeUrl;
use App\Models\AutomationRun;
use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends the request of a `send_webhook` step, in the run's workspace (tenant-aware by the
 * multitenancy default).
 *
 * - Every attempt vets the URL again with {@see SafeUrl} and pins the connection to the vetted
 *   addresses, so DNS changing between attempts (or mid-request) can't reach a private address.
 * - Redirects are not followed; a 3xx is the final answer. Timeout: 10 seconds to connect and in total.
 * - A connection error or 5xx is retried with backoff; any other status is final.
 * - Only the outcome (status, duration, attempt) is written to the run's log, never headers or bodies.
 * - A final failure (blocked URL, 3xx/4xx, or out of attempts) also tells the automation's creator. The run
 *   itself keeps its status: it already finished, or may be waiting on a delay.
 */
class SendAutomationWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public bool $deleteWhenMissingModels = true;

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public AutomationRun $run,
        public ?string $stepId,
        public string $method,
        public string $url,
        public array $headers = [],
        public ?string $body = null,
    ) {
        $this->onQueue('automations-http');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(SafeUrl $safeUrl, NotifyCreatorOfFailure $notifyCreator): void
    {
        $started = hrtime(true);

        try {
            $target = $safeUrl->check($this->url, Workspace::current()->settings->effectiveAllowPrivateUrls());
        } catch (AutomationException $exception) {
            $this->finish(null, $started, $exception->getMessage(), $notifyCreator);

            return;
        }

        try {
            $response = $this->request($safeUrl->curlOptions($target));
        } catch (ConnectionException) {
            if ($this->attempts() < $this->tries) {
                throw new AutomationException(__('The webhook could not connect.'));
            }

            $this->finish(null, $started, __('The webhook could not connect.'), $notifyCreator);

            return;
        }

        $status = $response->status();

        if ($status >= 500 && $this->attempts() < $this->tries) {
            throw new AutomationException(__('The webhook answered :status.', ['status' => $status]));
        }

        if ($response->successful()) {
            $this->finish($status, $started, null, $notifyCreator);
        } else {
            $this->finish($status, $started, __('The webhook answered with an error status.'), $notifyCreator);
        }
    }

    /**
     * Unexpected errors that used up the attempts (a timeout of the job itself, a bug).
     */
    public function failed(Throwable $exception): void
    {
        $this->finish(null, hrtime(true), __('The webhook failed unexpectedly.'), app(NotifyCreatorOfFailure::class));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function request(array $options): Response
    {
        $request = Http::withHeaders($this->headers)
            ->timeout(10)->connectTimeout(10)->withoutRedirecting()
            ->withOptions($options);

        $contentTypes = array_filter(array_keys($this->headers), fn (string $name): bool => strtolower($name) === 'content-type');

        if ($this->body !== null) {
            $request = $request->withBody($this->body, $contentTypes === [] ? 'application/json' : $this->headers[reset($contentTypes)]);
        }

        return $request->send($this->method, $this->url);
    }

    /**
     * Log the outcome on the run and, when it failed, tell the creator.
     */
    protected function finish(?int $status, int $started, ?string $error, NotifyCreatorOfFailure $notifyCreator): void
    {
        $entry = [
            'type' => 'webhook',
            'step_id' => $this->stepId,
            'status' => $status,
            'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'attempt' => $this->attempts(),
            ...($error === null ? [] : ['error' => $error]),
        ];

        DB::transaction(function () use ($entry): void {
            $run = AutomationRun::query()->whereKey($this->run->getKey())->lockForUpdate()->first();

            $run?->forceFill(['log' => [...$run->log, $entry]])->save();
        });

        if ($error !== null) {
            $this->run->error = __('The webhook failed: :error', ['error' => $error]);
            $notifyCreator->handle($this->run->automation, $this->run);
        }
    }
}
