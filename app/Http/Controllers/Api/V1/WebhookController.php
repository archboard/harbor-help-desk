<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Webhooks\CreateWebhook;
use App\Actions\Webhooks\DeleteWebhook;
use App\Actions\Webhooks\UpdateWebhook;
use App\Data\Webhooks\CreateWebhookData;
use App\Data\Webhooks\UpdateWebhookData;
use App\Data\Webhooks\WebhookData;
use App\Data\Webhooks\WebhookSecretData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class WebhookController extends Controller
{
    /**
     * Every webhook in the current workspace.
     *
     * @return Collection<int, WebhookData>
     */
    public function index(): Collection
    {
        Gate::authorize('viewAny', Webhook::class);

        return WebhookData::collect(Webhook::query()->orderBy('created_at')->orderBy('id')->get());
    }

    /**
     * The new webhook with its secret, shown once.
     */
    #[ApiBody(CreateWebhookData::class)]
    public function store(Request $request, #[CurrentUser] User $user, CreateWebhook $createWebhook): WebhookSecretData
    {
        return $createWebhook->handle($user, $request->all());
    }

    public function show(Webhook $webhook): WebhookData
    {
        Gate::authorize('view', $webhook);

        return WebhookData::from($webhook);
    }

    #[ApiBody(UpdateWebhookData::class)]
    public function update(Request $request, #[CurrentUser] User $user, Webhook $webhook, UpdateWebhook $updateWebhook): WebhookData
    {
        return WebhookData::from($updateWebhook->handle($user, $webhook, $request->all()));
    }

    public function destroy(#[CurrentUser] User $user, Webhook $webhook, DeleteWebhook $deleteWebhook): Response
    {
        $deleteWebhook->handle($user, $webhook);

        return response()->noContent();
    }
}
