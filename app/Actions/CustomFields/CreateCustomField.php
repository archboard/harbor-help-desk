<?php

namespace App\Actions\CustomFields;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateCustomField
{
    /**
     * Define a custom field in the current workspace. Only select and multi-select fields
     * take `options`.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): CustomField
    {
        Gate::forUser($actor)->authorize('create', CustomField::class);

        $type = CustomFieldType::tryFrom((string) ($input['type'] ?? ''));

        $validated = Validator::make($input, [
            'name' => CustomFieldNameRules::for(),
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'options' => $type?->hasOptions() ? ['required', 'array', 'min:1'] : ['prohibited'],
            'options.*' => ['string', 'max:255', 'distinct'],
            'is_required' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
        ])->validate();

        return CustomField::create($validated);
    }
}
