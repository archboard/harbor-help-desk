<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Webhooks\CreateWebhook;
use App\Actions\Webhooks\DeleteWebhook;
use App\Actions\Webhooks\ListWebhookDeliveries;
use App\Actions\Webhooks\UpdateWebhook;
use App\Data\Webhooks\WebhookData;
use App\Data\Webhooks\WebhookDeliveryData;
use App\Enums\TicketEventType;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    /**
     * The workspace's webhooks with their recent deliveries, for admins to manage.
     */
    public function index(#[CurrentUser] User $user, ListWebhookDeliveries $listWebhookDeliveries): Response
    {
        Gate::authorize('viewAny', Webhook::class);

        $webhooks = Webhook::query()->orderBy('created_at')->orderBy('id')->get();

        return Inertia::render('workspace/Webhooks', [
            'webhooks' => WebhookData::collect($webhooks),
            'deliveries' => $webhooks->mapWithKeys(fn (Webhook $webhook): array => [
                $webhook->id => WebhookDeliveryData::collect($listWebhookDeliveries->handle($user, $webhook)->items()),
            ]),
            'eventTypes' => TicketEventType::options(),
        ]);
    }

    /**
     * The secret is flashed for the next page only, since it is shown once.
     */
    public function store(Request $request, #[CurrentUser] User $user, CreateWebhook $createWebhook): RedirectResponse
    {
        Inertia::flash('webhookSecret', $createWebhook->handle($user, $request->all())->toArray());

        Flash::success(__('Webhook created.'));

        return back();
    }

    public function update(Request $request, #[CurrentUser] User $user, Webhook $webhook, UpdateWebhook $updateWebhook): RedirectResponse
    {
        $updateWebhook->handle($user, $webhook, $request->all());

        Flash::success(__('Webhook updated.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $user, Webhook $webhook, DeleteWebhook $deleteWebhook): RedirectResponse
    {
        $deleteWebhook->handle($user, $webhook);

        Flash::success(__('Webhook deleted.'));

        return back();
    }
}
