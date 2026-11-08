<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Webhooks\ListWebhookDeliveries;
use App\Data\Webhooks\WebhookDeliveryData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Container\Attributes\CurrentUser;
use Spatie\LaravelData\PaginatedDataCollection;

class WebhookDeliveryController extends Controller
{
    /**
     * The webhook's deliveries, newest first.
     *
     * @return PaginatedDataCollection<int, WebhookDeliveryData>
     */
    public function index(#[CurrentUser] User $user, Webhook $webhook, ListWebhookDeliveries $listWebhookDeliveries): PaginatedDataCollection
    {
        return WebhookDeliveryData::collect($listWebhookDeliveries->handle($user, $webhook), PaginatedDataCollection::class);
    }
}
