<?php

namespace App\Data\Automations\Actions;

class MoveDeskConfigData extends ActionConfigData
{
    public function __construct(
        public string $deskId,
    ) {}

    public static function rules(): array
    {
        return [
            'desk_id' => ['required', 'string'],
        ];
    }
}
