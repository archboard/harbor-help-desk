<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Data;

class WebhookReceivedData extends Data
{
    public function __construct(
        public string $runId,
    ) {}
}
