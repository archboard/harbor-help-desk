<?php

namespace App\Data\Automations\Actions;

use App\Automations\TagsMode;
use Illuminate\Validation\Rule;

class TagsConfigData extends ActionConfigData
{
    /**
     * @param  list<string>  $names
     */
    public function __construct(
        public TagsMode $mode,
        public array $names,
    ) {}

    public static function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(TagsMode::class)],
            'names' => ['present', 'array'],
            'names.*' => ['required', 'string', 'max:255'],
        ];
    }
}
