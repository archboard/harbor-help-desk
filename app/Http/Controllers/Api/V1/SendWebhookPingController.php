<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Webhooks\SendWebhookPing;
use App\Data\Webhooks\WebhookDeliveryData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Container\Attributes\CurrentUser;

class SendWebhookPingController extends Controller
{
    /**
     * Send a test event now and return how it went.
     */
    public function __invoke(#[CurrentUser] User $user, Webhook $webhook, SendWebhookPing $sendWebhookPing): WebhookDeliveryData
    {
        return WebhookDeliveryData::from($sendWebhookPing->handle($user, $webhook));
    }
}
