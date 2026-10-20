<?php

namespace App\Data\CustomFields;

use App\Data\InputData;
use App\Enums\CustomFieldType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Optional;

/**
 * The name's uniqueness, the fixed type and whether `options` are allowed depend on the field
 * being updated, so `UpdateCustomField` checks them.
 */
class UpdateCustomFieldData extends InputData
{
    /**
     * @param  list<string>|Optional  $options
     */
    public function __construct(
        public string|Optional $name,
        public CustomFieldType|Optional $type,
        public array|Optional $options,
        public bool|Optional $isRequired,
        #[IntegerType]
        public int|Optional $position,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(CustomFieldType::class)],
            'options' => ['sometimes', 'required', 'array', 'min:1'],
            'options.*' => ['string', 'max:255', 'distinct'],
        ];
    }
}
