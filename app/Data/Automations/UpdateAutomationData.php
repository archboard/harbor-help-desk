<?php

namespace App\Data\Automations;

use App\Automations\DefinitionValidator;
use App\Data\InputData;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * Enabling goes through {@see SetAutomationEnabledData}.
 */
class UpdateAutomationData extends InputData
{
    /**
     * @param  array<string, mixed>|Optional  $definition
     */
    public function __construct(
        public string|Optional $name,
        public string|Optional|null $description,
        public string|Optional|null $deskId,
        #[LiteralTypeScriptType('App.Data.Automations.AutomationDefinitionData')] public array|Optional $definition,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'desk_id' => ['sometimes', 'nullable', 'string', CreateAutomationData::deskExists()],
            'definition' => ['sometimes', 'bail', 'required', 'array', app(DefinitionValidator::class)],
        ];
    }
}
