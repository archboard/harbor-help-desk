<?php

namespace App\Actions\CustomFields;

use App\Models\CustomField;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteCustomField
{
    /**
     * Delete the field and strip its values from the workspace's tickets. The ticket events
     * keep the history of what they were.
     */
    public function handle(User $actor, CustomField $field): void
    {
        Gate::forUser($actor)->authorize('delete', $field);

        DB::transaction(function () use ($field): void {
            DB::update(
                'update tickets set custom_fields = custom_fields - ?::text where workspace_id = ? and jsonb_exists(custom_fields, ?)',
                [$field->getKey(), $field->workspace_id, $field->getKey()],
            );

            $field->delete();
        });
    }
}
