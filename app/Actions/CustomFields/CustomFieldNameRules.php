<?php

namespace App\Actions\CustomFields;

use App\Models\CustomField;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rules for a custom field name: unique in the current workspace, ignoring case.
 */
class CustomFieldNameRules
{
    /**
     * @return array<int, ValidationRule|Closure|string>
     */
    public static function for(?CustomField $ignore = null): array
    {
        return [
            'required',
            'string',
            'max:255',
            function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
                $taken = CustomField::query()
                    ->whereRaw('lower(name) = lower(?)', [$value])
                    ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                    ->exists();

                if ($taken) {
                    $fail(__('validation.unique'));
                }
            },
        ];
    }
}
