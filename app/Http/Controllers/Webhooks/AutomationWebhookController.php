<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Automations\ReceiveAutomationWebhook;
use App\Data\Automations\WebhookReceivedData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Landlord code: the secret URL of a `webhook` automation, outside auth and the workspace middleware.
 */
class AutomationWebhookController extends Controller
{
    public function __invoke(Request $request, string $automation, string $token, ReceiveAutomationWebhook $receiveAutomationWebhook): JsonResponse
    {
        $run = $receiveAutomationWebhook->handle($automation, $token, $request->getContent());

        return response()->json(new WebhookReceivedData($run->getKey()), 202);
    }
}
