<?php

namespace App\Actions\Webhooks;

use App\Automations\SafeUrl;
use App\Data\Webhooks\CreateWebhookData;
use App\Data\Webhooks\WebhookData;
use App\Data\Webhooks\WebhookSecretData;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\LaravelData\Optional;

class CreateWebhook
{
    public function __construct(protected SafeUrl $safeUrl) {}

    /**
     * Create an enabled webhook with a new secret. The secret is only available on the result.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): WebhookSecretData
    {
        Gate::forUser($actor)->authorize('create', Webhook::class);

        $workspace = Workspace::current();

        $data = CreateWebhookData::validateAndCreateWith($input, [
            'url' => [$this->safeUrl->rule($workspace->settings->effectiveAllowPrivateUrls())],
        ]);

        $secret = 'whsec_'.Str::random(40);

        $webhook = Webhook::create([
            'workspace_id' => $workspace->getKey(),
            'url' => $data->url,
            'description' => $data->description instanceof Optional ? null : $data->description,
            'event_types' => $data->eventTypes instanceof Optional ? [] : array_values(array_unique($data->eventTypes)),
            'secret' => $secret,
            'is_enabled' => true,
            'consecutive_failures' => 0,
            'created_by' => $actor->getKey(),
        ]);

        AuditEvent::record(AuditAction::WEBHOOK_CREATED, $actor, $webhook);

        return new WebhookSecretData(WebhookData::from($webhook), $secret);
    }
}
