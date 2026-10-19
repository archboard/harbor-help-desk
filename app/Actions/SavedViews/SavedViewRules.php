<?php

namespace App\Actions\SavedViews;

use App\Actions\Tickets\SearchTickets;

/**
 * A view's filters are exactly what {@see SearchTickets} accepts, checked with its rules.
 */
class SavedViewRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, mixed>>
     */
    public static function for(array $input): array
    {
        $filters = is_array($input['filters'] ?? null) ? $input['filters'] : [];

        $filterRules = collect(SearchTickets::rules($filters))
            ->mapWithKeys(fn (array $rules, string $key): array => ["filters.{$key}" => $rules])
            ->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'filters' => ['present', 'array:'.implode(',', SearchTickets::FILTERS)],
            'is_shared' => ['sometimes', 'boolean'],
            ...$filterRules,
        ];
    }
}
