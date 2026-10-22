<?php

namespace App\Data\Automations;

use App\Data\InputData;

class SetAutomationEnabledData extends InputData
{
    public function __construct(
        public bool $enabled,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }
}
