<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\RotateWebhookToken;
use App\Data\Automations\AutomationWebhookTokenData;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class RotateWebhookTokenController extends Controller
{
    /**
     * The new secret URL, shown once.
     */
    public function __invoke(#[CurrentUser] User $user, Automation $automation, RotateWebhookToken $rotateWebhookToken): AutomationWebhookTokenData
    {
        return $rotateWebhookToken->handle($user, $automation);
    }
}
