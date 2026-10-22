<?php

namespace App\Data\Automations\Actions;

use App\Automations\AssignMode;
use Illuminate\Validation\Rule;

class AssignConfigData extends ActionConfigData
{
    /**
     * @param  list<string>|null  $userIds  For `add` and `replace`.
     * @param  string|null  $deskId  For `round_robin` and `load_balanced`: the desk whose rotation picks.
     */
    public function __construct(
        public AssignMode $mode,
        public ?array $userIds = null,
        public ?string $deskId = null,
    ) {}

    public static function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(AssignMode::class)],
            'user_ids' => ['nullable', 'array', 'required_if:mode,add,replace'],
            'user_ids.*' => ['string', 'distinct'],
            'desk_id' => ['nullable', 'string', 'required_if:mode,round_robin,load_balanced'],
        ];
    }
}
