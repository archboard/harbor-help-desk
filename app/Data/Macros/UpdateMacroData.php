<?php

namespace App\Data\Macros;

use App\Data\InputData;
use Spatie\LaravelData\Optional;

/**
 * A macro's scope is fixed once created. That it keeps a body or some actions depends on the
 * macro being updated, so `UpdateMacro` checks it.
 */
class UpdateMacroData extends InputData
{
    /**
     * @param  array<string, mixed>|Optional  $actions
     */
    public function __construct(
        public string|Optional $name,
        public string|Optional|null $body,
        public bool|Optional $bodyIsInternal,
        public array|Optional $actions,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string'],
            'body_is_internal' => ['sometimes', 'boolean'],
            'actions' => ['sometimes', 'array:status,priority,add_tags,remove_tags,assign_to_me'],
            ...CreateMacroData::actionRules(),
        ];
    }
}
