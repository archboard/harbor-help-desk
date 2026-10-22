<?php

namespace App\Data\Automations;

use App\Data\InputData;

class ReceiveAutomationWebhookData extends InputData
{
    /**
     * @param  array<string, mixed>  $payload  A JSON object or list.
     */
    public function __construct(
        public array $payload,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'payload' => ['present', 'array'],
        ];
    }
}
