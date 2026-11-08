<?php

namespace App\Jobs;

use App\Actions\Webhooks\DeliverWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Sends one webhook delivery, in the delivery's workspace (tenant-aware by the multitenancy
 * default). A connection error or 5xx is retried with backoff; the outcome and the failure
 * count are handled by {@see DeliverWebhook}.
 */
class SendTicketWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public WebhookDelivery $delivery)
    {
        $this->onQueue('automations-http');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(DeliverWebhook $deliverWebhook): void
    {
        if ($deliverWebhook->handle($this->delivery, $this->attempts(), $this->attempts() >= $this->tries)) {
            throw new RuntimeException('The webhook delivery failed and will be retried.');
        }
    }

    /**
     * Unexpected errors that used up the attempts (a timeout of the job itself, a bug).
     */
    public function failed(Throwable $exception): void
    {
        app(DeliverWebhook::class)->fail($this->delivery, $this->attempts(), __('The webhook failed unexpectedly.'));
    }
}
