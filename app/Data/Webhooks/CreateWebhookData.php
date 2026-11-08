<?php

namespace App\Data\Webhooks;

use App\Automations\SafeUrl;
use App\Data\InputData;
use App\Enums\TicketEventType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;

/**
 * An empty `event_types` subscribes to every event. The URL must pass {@see SafeUrl}, which
 * needs DNS and the workspace's settings, so `CreateWebhook` and `UpdateWebhook` check that.
 */
class CreateWebhookData extends InputData
{
    /**
     * @param  list<string>|Optional  $eventTypes  Values of {@see TicketEventType}.
     */
    public function __construct(
        public string $url,
        public string|null|Optional $description,
        public array|Optional $eventTypes,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return self::rulesWith('required');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rulesWith(string $presence): array
    {
        return [
            'url' => [$presence, 'string', 'max:'.SafeUrl::MAX_LENGTH],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'event_types' => ['sometimes', 'array'],
            'event_types.*' => [Rule::enum(TicketEventType::class)],
        ];
    }
}
