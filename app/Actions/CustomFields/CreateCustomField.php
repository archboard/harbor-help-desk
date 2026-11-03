<?php

namespace App\Actions\CustomFields;

use App\Data\CustomFields\CreateCustomFieldData;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

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

        $field = CustomField::create(CreateCustomFieldData::validateAndCreate($input)->filled());

        AuditEvent::record(AuditAction::CUSTOM_FIELD_CREATED, $actor, $field);

        return $field;
    }
}
