<?php

namespace App\Actions\Webhooks;

use App\Automations\SafeUrl;
use App\Data\Webhooks\UpdateWebhookData;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

class UpdateWebhook
{
    public function __construct(protected SafeUrl $safeUrl) {}

    /**
     * Enabling a webhook again clears its failure count and `disabled_at`.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Webhook $webhook, array $input): Webhook
    {
        Gate::forUser($actor)->authorize('update', $webhook);

        $data = UpdateWebhookData::validateAndCreateWith($input, [
            'url' => [$this->safeUrl->rule($webhook->workspace->settings->effectiveAllowPrivateUrls())],
        ]);

        $changes = $data->filled();

        if (! $data->eventTypes instanceof Optional) {
            $changes['event_types'] = array_values(array_unique($data->eventTypes));
        }

        if ($data->isEnabled === true) {
            $changes += ['consecutive_failures' => 0, 'disabled_at' => null];
        } elseif ($data->isEnabled === false && $webhook->is_enabled) {
            $changes['disabled_at'] = now();
        }

        $webhook->update($changes);

        AuditEvent::record(AuditAction::WEBHOOK_UPDATED, $actor, $webhook, ['changed' => array_keys($data->filled())]);

        return $webhook;
    }
}
