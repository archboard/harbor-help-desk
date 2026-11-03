<?php

namespace App\Actions\Automations;

use App\Data\Automations\AutomationWebhookTokenData;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RotateWebhookToken
{
    /**
     * Issue a new secret for a `webhook` automation, which stops the old one working. Only its
     * sha256 hash is stored, so the plain token is only available on the result.
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Automation $automation): AutomationWebhookTokenData
    {
        Gate::forUser($actor)->authorize('update', $automation);

        if ($automation->trigger_type !== 'webhook') {
            throw ValidationException::withMessages(['automation' => __('Only automations triggered by a webhook have a secret URL.')]);
        }

        $token = Str::random(40);

        $automation->forceFill(['webhook_token_hash' => hash('sha256', $token), 'updated_by' => $actor->getKey()])->save();

        AuditEvent::record(AuditAction::AUTOMATION_WEBHOOK_TOKEN_ROTATED, $actor, $automation);

        return new AutomationWebhookTokenData(route('webhooks.automations', [$automation->getKey(), $token]), $token);
    }
}
