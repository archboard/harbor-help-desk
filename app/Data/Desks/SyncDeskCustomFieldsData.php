<?php

namespace App\Data\Desks;

use App\Data\InputData;
use App\Models\Workspace;
use Illuminate\Validation\Rule;

class SyncDeskCustomFieldsData extends InputData
{
    /**
     * @param  list<string>  $fieldIds
     */
    public function __construct(
        public array $fieldIds,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'field_ids' => ['present', 'array'],
            'field_ids.*' => ['string', Rule::exists('custom_fields', 'id')->where('workspace_id', Workspace::current()?->getKey())],
        ];
    }
}
