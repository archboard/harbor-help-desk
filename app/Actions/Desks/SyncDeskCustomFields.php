<?php

namespace App\Actions\Desks;

use App\Models\Desk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
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

        $ids = Validator::make($input, [
            'field_ids' => ['present', 'array'],
            'field_ids.*' => ['string', Rule::exists('custom_fields', 'id')->where('workspace_id', $desk->workspace_id)],
        ])->safe()->collect('field_ids');

        $desk->customFields()->sync(
            $ids->mapWithKeys(fn (string $id): array => [$id => ['workspace_id' => $desk->workspace_id]])->all(),
        );

        $desk->unsetRelation('customFields');
    }
}
