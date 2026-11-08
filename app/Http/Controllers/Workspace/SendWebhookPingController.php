<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Webhooks\SendWebhookPing;
use App\Enums\WebhookDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class SendWebhookPingController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, Webhook $webhook, SendWebhookPing $sendWebhookPing): RedirectResponse
    {
        $delivery = $sendWebhookPing->handle($user, $webhook);

        if ($delivery->status === WebhookDeliveryStatus::SUCCEEDED) {
            Flash::success(__('The test event was delivered.'));
        } else {
            Flash::error(__('The test event failed: :error', ['error' => $delivery->error ?? __('no response')]));
        }

        return back();
    }
}
