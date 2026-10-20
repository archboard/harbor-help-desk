<?php

namespace App\Data\SavedViews;

use App\Data\InputData;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * The same rules as {@see CreateSavedViewData}, each optional.
 */
class UpdateSavedViewData extends InputData
{
    /**
     * @param  array<string, mixed>|Optional  $filters
     */
    public function __construct(
        public string|Optional $name,
        public array|Optional $filters,
        public bool|Optional $isShared,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        return array_map(fn (array $rules): array => ['sometimes', ...$rules], CreateSavedViewData::rules($context));
    }
}
