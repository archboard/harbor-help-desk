<?php

namespace App\Actions\Webhooks;

use App\Automations\AutomationException;
use App\Automations\SafeUrl;
use App\Data\Tickets\TicketData;
use App\Data\Tickets\TicketEventData;
use App\Data\Webhooks\WebhookPayloadData;
use App\Enums\AuditAction;
use App\Enums\WebhookDeliveryStatus;
use App\Models\AuditEvent;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\Workspace;
use App\Notifications\WebhookDisabledNotification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Notification;

class DeliverWebhook
{
    public function __construct(protected SafeUrl $safeUrl) {}

    /**
     * Make one attempt at a delivery. Returns true when the attempt failed in a way worth
     * retrying (no connection, or a 5xx) and it wasn't the last one; the delivery stays pending.
     * Anything else settles it: it succeeded, or failed for good, which counts towards disabling
     * the webhook. Pings never do.
     *
     * Every attempt vets the URL again and pins the connection to the vetted addresses. The
     * signature covers the timestamp and the exact body: `HMAC-SHA256(secret, "{t}.{body}")`.
     */
    public function handle(WebhookDelivery $delivery, int $attempt, bool $lastAttempt): bool
    {
        $webhook = $delivery->webhook;
        $started = hrtime(true);

        if (! $webhook->is_enabled && ! $delivery->isPing()) {
            $this->settle($delivery, $attempt, $started, null, __('The webhook is disabled.'), count: false);

            return false;
        }

        $body = $this->payload($delivery)->toJson();
        $timestamp = now()->timestamp;

        try {
            $response = $this->safeUrl->send('POST', $webhook->url, [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Harbor-Webhooks',
                'Harbor-Delivery' => $delivery->id,
                'Harbor-Signature' => "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$body}", $webhook->secret),
            ], $body, Workspace::current()->settings->effectiveAllowPrivateUrls());
        } catch (AutomationException $exception) {
            $this->settle($delivery, $attempt, $started, null, $exception->getMessage());

            return false;
        } catch (ConnectionException) {
            return $this->failed($delivery, $attempt, $lastAttempt, $started, null, __('The webhook could not connect.'));
        }

        $status = $response->status();

        if ($response->successful()) {
            $this->settle($delivery, $attempt, $started, $status, null);

            return false;
        }

        return $this->failed($delivery, $attempt, $lastAttempt, $started, $status, __('The webhook answered :status.', ['status' => $status]), retry: $status >= 500);
    }

    /**
     * A final failure that wasn't an HTTP answer, such as the job timing out.
     */
    public function fail(WebhookDelivery $delivery, int $attempt, string $error): void
    {
        $this->settle($delivery, $attempt, hrtime(true), null, $error);
    }

    protected function failed(WebhookDelivery $delivery, int $attempt, bool $lastAttempt, int $started, ?int $status, string $error, bool $retry = true): bool
    {
        if ($retry && ! $lastAttempt) {
            $delivery->update(['attempts' => $attempt, 'response_status' => $status, 'error' => $error]);

            return true;
        }

        $this->settle($delivery, $attempt, $started, $status, $error);

        return false;
    }

    protected function settle(WebhookDelivery $delivery, int $attempt, int $started, ?int $status, ?string $error, bool $count = true): void
    {
        $delivery->update([
            'status' => $error === null ? WebhookDeliveryStatus::SUCCEEDED : WebhookDeliveryStatus::FAILED,
            'attempts' => $attempt,
            'response_status' => $status,
            'error' => $error,
            'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'delivered_at' => $error === null ? now() : null,
        ]);

        if ($count && ! $delivery->isPing()) {
            $error === null ? $this->recordSuccess($delivery->webhook) : $this->recordFailure($delivery->webhook);
        }
    }

    protected function recordSuccess(Webhook $webhook): void
    {
        Webhook::query()->whereKey($webhook->getKey())->where('consecutive_failures', '>', 0)->update(['consecutive_failures' => 0]);
    }

    /**
     * The conditional update lets exactly one of several concurrent failures disable the webhook.
     */
    protected function recordFailure(Webhook $webhook): void
    {
        Webhook::query()->whereKey($webhook->getKey())->increment('consecutive_failures');

        $disabled = Webhook::query()
            ->whereKey($webhook->getKey())
            ->where('is_enabled', true)
            ->where('consecutive_failures', '>=', Webhook::MAX_CONSECUTIVE_FAILURES)
            ->update(['is_enabled' => false, 'disabled_at' => now()]);

        if ($disabled === 0) {
            return;
        }

        $webhook->refresh();

        AuditEvent::record(AuditAction::WEBHOOK_DISABLED, null, $webhook);

        $creator = $webhook->creator;

        if ($creator !== null && $webhook->workspace->users()->whereKey($creator->getKey())->exists()) {
            Notification::sendNow($creator, new WebhookDisabledNotification($webhook));
        }
    }

    protected function payload(WebhookDelivery $delivery): WebhookPayloadData
    {
        $event = $delivery->ticketEvent;
        $ticket = $event?->ticket?->load(['requester', 'assignees', 'tags', 'slaTargets']);

        return new WebhookPayloadData(
            id: $delivery->id,
            type: $delivery->event_type,
            occurredAt: $event === null ? now()->toImmutable() : $event->created_at,
            workspaceId: $delivery->workspace_id,
            event: $event === null ? null : TicketEventData::from($event),
            ticket: $ticket === null ? null : TicketData::from($ticket),
        );
    }
}
