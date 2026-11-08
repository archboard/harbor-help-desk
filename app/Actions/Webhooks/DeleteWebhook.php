<?php

namespace App\Actions\Webhooks;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Support\Facades\Gate;

class DeleteWebhook
{
    /**
     * Its delivery history goes with it.
     */
    public function handle(User $actor, Webhook $webhook): void
    {
        Gate::forUser($actor)->authorize('delete', $webhook);

        $webhook->delete();

        AuditEvent::record(AuditAction::WEBHOOK_DELETED, $actor, $webhook);
    }
}
