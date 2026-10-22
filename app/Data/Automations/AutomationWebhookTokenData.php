<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;

/**
 * The secret URL of a webhook automation. The token is only ever shown here, once.
 */
class AutomationWebhookTokenData extends Data
{
    public function __construct(
        public string $url,
        public string $token,
    ) {}
}
