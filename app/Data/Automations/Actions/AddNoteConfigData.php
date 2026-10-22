<?php

namespace App\Data\Automations\Actions;

class AddNoteConfigData extends ActionConfigData
{
    /**
     * @param  string  $body  Placeholders are filled in when it runs.
     */
    public function __construct(
        public string $body,
    ) {}

    public static function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
