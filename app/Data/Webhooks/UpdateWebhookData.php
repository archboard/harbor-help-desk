<?php

namespace App\Data\Webhooks;

use App\Data\InputData;
use App\Enums\TicketEventType;
use Spatie\LaravelData\Optional;

/**
 * Only the fields that are sent change. Enabling a webhook again resets its failure count.
 */
class UpdateWebhookData extends InputData
{
    /**
     * @param  list<string>|Optional  $eventTypes  Values of {@see TicketEventType}.
     */
    public function __construct(
        public string|Optional $url,
        public string|null|Optional $description,
        public array|Optional $eventTypes,
        public bool|Optional $isEnabled,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [...CreateWebhookData::rulesWith('sometimes'), 'is_enabled' => ['sometimes', 'boolean']];
    }
}
