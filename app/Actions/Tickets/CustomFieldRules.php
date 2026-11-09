<?php

namespace App\Actions\Tickets;

use App\Models\CustomField;
use App\Models\Desk;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\In;

/**
 * Validation rules and storage casting for the `custom_fields` input of a ticket on a desk.
 */
class CustomFieldRules
{
    /**
     * Rules for `custom_fields` and each field the desk has enabled. Fields the desk hasn't
     * enabled are rejected. A required field must be given on create and can't be cleared
     * on update, but updates that leave it out are fine so tickets that predate it can change.
     *
     * @return array<string, array<int, ValidationRule|In|string>>
     */
    public static function for(Desk $desk, bool $creating): array
    {
        $fields = $desk->loadMissing('customFields')->customFields;

        $rules = [
            'custom_fields' => $fields->isEmpty()
                ? ['prohibited']
                : ['sometimes', 'array:'.implode(',', $fields->modelKeys())],
        ];

        foreach ($fields as $field) {
            $presence = match (true) {
                ! $field->is_required => ['sometimes', 'nullable'],
                $creating => ['required'],
                default => ['sometimes', 'required'],
            };

            $rules["custom_fields.{$field->id}"] = [...$presence, ...$field->type->rules($field)];

            if ($field->type->itemRules($field) !== []) {
                $rules["custom_fields.{$field->id}.*"] = $field->type->itemRules($field);
            }
        }

        return $rules;
    }

    /**
     * Cast validated form values to the JSON scalars they're stored as, dropping the nulls.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function cast(Desk $desk, array $values): array
    {
        return $desk->loadMissing('customFields')->customFields
            ->filter(fn (CustomField $field): bool => ($values[$field->id] ?? null) !== null)
            ->mapWithKeys(fn (CustomField $field): array => [$field->id => $field->type->cast($values[$field->id])])
            ->all();
    }
}
