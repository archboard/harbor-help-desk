<?php

namespace App\Data\Automations;

use App\Automations\DefinitionValidator;
use App\Automations\Templates;
use App\Data\InputData;
use App\Models\Workspace;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * The `definition` is checked against the registry by {@see DefinitionValidator}, which reports
 * errors by step id, so the shape of its steps isn't declared here.
 */
class CreateAutomationData extends InputData
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(
        public string $name,
        public string|Optional|null $description,
        public string|Optional|null $deskId,
        public bool|Optional $enabled,
        public string|Optional|null $template,
        #[LiteralTypeScriptType('App.Data.Automations.AutomationDefinitionData')] public array $definition,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'desk_id' => ['nullable', 'string', self::deskExists()],
            'enabled' => ['sometimes', 'boolean'],
            'template' => ['nullable', 'string', Rule::in(Templates::KEYS)],
            'definition' => ['bail', 'required', 'array', app(DefinitionValidator::class)],
        ];
    }

    public static function deskExists(): mixed
    {
        return Rule::exists('desks', 'id')->where('workspace_id', Workspace::current()?->getKey())->whereNull('archived_at');
    }
}
