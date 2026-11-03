<?php

namespace App\Actions\Desks;

use App\Data\Desks\SyncDeskCustomFieldsData;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SyncDeskCustomFields
{
    /**
     * Make `field_ids` the custom fields the desk's tickets use.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Desk $desk, array $input): void
    {
        Gate::forUser($actor)->authorize('update', $desk);

        $ids = SyncDeskCustomFieldsData::validateAndCreateWith($input, [
            'field_ids.*' => [Rule::exists('custom_fields', 'id')->where('workspace_id', $desk->workspace_id)],
        ])->fieldIds;

        $desk->customFields()->sync(
            collect($ids)->mapWithKeys(fn (string $id): array => [$id => ['workspace_id' => $desk->workspace_id]])->all(),
        );

        $desk->unsetRelation('customFields');

        AuditEvent::record(AuditAction::DESK_CUSTOM_FIELDS_SYNCED, $actor, $desk, ['field_ids' => $ids]);
    }
}
