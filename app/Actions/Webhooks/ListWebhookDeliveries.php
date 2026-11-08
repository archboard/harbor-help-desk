<?php

namespace App\Actions\Webhooks;

use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class ListWebhookDeliveries
{
    public const int PER_PAGE = 25;

    /**
     * The webhook's deliveries, newest first.
     *
     * @return LengthAwarePaginator<int, WebhookDelivery>
     */
    public function handle(User $actor, Webhook $webhook): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('view', $webhook);

        return $webhook->deliveries()->latest()->latest('id')->paginate(self::PER_PAGE);
    }
}
