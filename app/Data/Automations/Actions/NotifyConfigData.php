<?php

namespace App\Data\Automations\Actions;

use App\Automations\NotifyRecipients;
use Illuminate\Validation\Rule;

class NotifyConfigData extends ActionConfigData
{
    /**
     * @param  list<string>|null  $userIds  For `users`.
     * @param  string  $subject  The email subject and notification title. Placeholders are filled in when it runs.
     * @param  string  $body  The message. Placeholders are filled in when it runs.
     */
    public function __construct(
        public NotifyRecipients $recipients,
        public string $subject,
        public string $body,
        public ?array $userIds = null,
    ) {}

    public static function rules(): array
    {
        return [
            'recipients' => ['required', Rule::enum(NotifyRecipients::class)],
            'user_ids' => ['nullable', 'array', 'required_if:recipients,users'],
            'user_ids.*' => ['string', 'distinct'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
