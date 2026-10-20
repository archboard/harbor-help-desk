<?php

namespace App\Data\Desks;

use App\Data\InputData;

/**
 * The fields must belong to the desk's workspace, so `SyncDeskCustomFields` checks that they exist.
 */
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
            'field_ids.*' => ['string'],
        ];
    }
}
