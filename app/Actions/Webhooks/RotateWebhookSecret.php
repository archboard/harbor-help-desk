<?php

namespace App\Actions\Webhooks;

use App\Data\Webhooks\WebhookData;
use App\Data\Webhooks\WebhookSecretData;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RotateWebhookSecret
{
    /**
     * Issue a new signing secret, which stops the old one verifying. It is only available on the result.
     */
    public function handle(User $actor, Webhook $webhook): WebhookSecretData
    {
        Gate::forUser($actor)->authorize('update', $webhook);

        $secret = 'whsec_'.Str::random(40);

        $webhook->update(['secret' => $secret]);

        AuditEvent::record(AuditAction::WEBHOOK_SECRET_ROTATED, $actor, $webhook);

        return new WebhookSecretData(WebhookData::from($webhook), $secret);
    }
}
