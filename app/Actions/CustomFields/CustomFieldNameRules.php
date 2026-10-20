<?php

namespace App\Actions\CustomFields;

use App\Models\CustomField;
use Closure;

/**
 * Custom field names are unique in the current workspace, ignoring case.
 */
class CustomFieldNameRules
{
    public static function unique(?CustomField $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $taken = CustomField::query()
                ->whereRaw('lower(name) = lower(?)', [$value])
                ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                ->exists();

            if ($taken) {
                $fail(__('validation.unique'));
            }
        };
    }
}
