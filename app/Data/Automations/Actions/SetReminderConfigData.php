<?php

namespace App\Data\Automations\Actions;

use App\Data\Automations\DelayForData;

class SetReminderConfigData extends ActionConfigData
{
    /**
     * @param  DelayForData  $after  How long from now the reminder is due.
     * @param  string|null  $note  Placeholders are filled in when it runs.
     */
    public function __construct(
        public DelayForData $after,
        public ?string $note = null,
    ) {}

    public static function rules(): array
    {
        return [
            ...DelayForData::rulesAt('after'),
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
