<?php

namespace App\Data\Automations;

use App\Automations\DefinitionValidator;
use App\Data\InputData;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * `enabled` switches the automation in the same save; on its own it is {@see SetAutomationEnabledData}.
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
        public bool|Optional $enabled,
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
            'enabled' => ['sometimes', 'boolean'],
            'desk_id' => ['sometimes', 'nullable', 'string', CreateAutomationData::deskExists()],
            'definition' => ['sometimes', 'bail', 'required', 'array', app(DefinitionValidator::class)],
        ];
    }
}
