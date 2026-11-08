<?php

namespace App\Data\Webhooks;

use Spatie\LaravelData\Data;

/**
 * A webhook with its signing secret. The secret is only ever shown here, once, when the
 * webhook is created or its secret rotated.
 */
class WebhookSecretData extends Data
{
    public function __construct(
        public WebhookData $webhook,
        public string $secret,
    ) {}
}
