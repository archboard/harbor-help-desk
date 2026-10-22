<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\RotateWebhookToken;
use App\Data\Automations\AutomationWebhookTokenData;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use Illuminate\Http\Request;

class RotateWebhookTokenController extends Controller
{
    /**
     * The new secret URL, shown once.
     */
    public function __invoke(Request $request, Automation $automation, RotateWebhookToken $rotateWebhookToken): AutomationWebhookTokenData
    {
        return $rotateWebhookToken->handle($request->user(), $automation);
    }
}
