<?php

namespace App\Data\SavedViews;

use App\Actions\Tickets\SearchTickets;
use App\Data\InputData;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * A view's filters are exactly what {@see SearchTickets} accepts, checked with its rules.
 */
class CreateSavedViewData extends InputData
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public string $name,
        public array $filters,
        public bool|Optional $isShared,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $filters = is_array($context->payload['filters'] ?? null) ? $context->payload['filters'] : [];

        return [
            'name' => ['required', 'string', 'max:255'],
            'filters' => ['present', 'array:'.implode(',', SearchTickets::FILTERS)],
            'is_shared' => ['sometimes', 'boolean'],
            ...collect(SearchTickets::rules($filters))
                ->mapWithKeys(fn (array $rules, string $key): array => ["filters.{$key}" => $rules])
                ->all(),
        ];
    }
}
