<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Webhooks\RotateWebhookSecret;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RotateWebhookSecretController extends Controller
{
    /**
     * The new secret is flashed for the next page only, since it is shown once.
     */
    public function __invoke(#[CurrentUser] User $user, Webhook $webhook, RotateWebhookSecret $rotateWebhookSecret): RedirectResponse
    {
        Inertia::flash('webhookSecret', $rotateWebhookSecret->handle($user, $webhook)->toArray());

        Flash::success(__('Secret rotated.'));

        return back();
    }
}
