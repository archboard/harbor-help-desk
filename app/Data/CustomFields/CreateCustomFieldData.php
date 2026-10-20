<?php

namespace App\Data\CustomFields;

use App\Actions\CustomFields\CustomFieldNameRules;
use App\Data\InputData;
use App\Enums\CustomFieldType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * Only select and multi-select fields take `options`.
 */
class CreateCustomFieldData extends InputData
{
    /**
     * @param  list<string>|Optional  $options
     */
    public function __construct(
        public string $name,
        public CustomFieldType $type,
        public array|Optional $options,
        public bool|Optional $isRequired,
        #[IntegerType]
        public int|Optional $position,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $type = CustomFieldType::tryFrom((string) ($context->payload['type'] ?? ''));

        return [
            'name' => ['required', 'string', 'max:255', CustomFieldNameRules::unique()],
            'options' => $type?->hasOptions() ? ['required', 'array', 'min:1'] : ['prohibited'],
            'options.*' => ['string', 'max:255', 'distinct'],
        ];
    }
}
