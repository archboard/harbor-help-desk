<?php

namespace App\Data\Automations\Actions;

class ApplyMacroConfigData extends ActionConfigData
{
    public function __construct(
        public string $macroId,
    ) {}

    public static function rules(): array
    {
        return [
            'macro_id' => ['required', 'string'],
        ];
    }
}
