<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Webhooks\RotateWebhookSecret;
use App\Data\Webhooks\WebhookSecretData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Container\Attributes\CurrentUser;

class RotateWebhookSecretController extends Controller
{
    /**
     * The new secret, shown once.
     */
    #[ApiBody]
    public function __invoke(#[CurrentUser] User $user, Webhook $webhook, RotateWebhookSecret $rotateWebhookSecret): WebhookSecretData
    {
        return $rotateWebhookSecret->handle($user, $webhook);
    }
}
