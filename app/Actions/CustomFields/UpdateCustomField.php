<?php

namespace App\Actions\CustomFields;

use App\Data\CustomFields\UpdateCustomFieldData;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateCustomField
{
    /**
     * Change a custom field's name, options, required flag or position. The type is fixed
     * because existing values would stop matching it.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, CustomField $field, array $input): CustomField
    {
        Gate::forUser($actor)->authorize('update', $field);

        // ponytail: removing a select option leaves tickets that already use it as they are.
        // Rename or migrate their values here if that ever matters.
        $data = UpdateCustomFieldData::validateAndCreate($input);
        Validator::make($input, [
            'name' => ['sometimes', CustomFieldNameRules::unique($field)],
            'type' => ['sometimes', Rule::in([$field->type->value])],
            'options' => $field->type->hasOptions() ? [] : ['prohibited'],
        ])->validate();

        $field->update($data->filled());

        return $field;
    }
}
