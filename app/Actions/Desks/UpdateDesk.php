<?php

namespace App\Actions\Desks;

use App\Data\Desks\UpdateDeskData;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateDesk
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Desk $desk, array $input): Desk
    {
        Gate::forUser($actor)->authorize('update', $desk);

        $data = UpdateDeskData::validateAndCreateWith($input, [
            'slug' => ['sometimes', DeskSlugRules::unique($desk->workspace, $desk)],
            'sla_policy_id' => ['nullable', Rule::exists('sla_policies', 'id')->where('workspace_id', $desk->workspace_id)],
        ]);

        $desk->update($data->filled());

        AuditEvent::record(AuditAction::DESK_UPDATED, $actor, $desk, ['changed' => array_keys($data->filled())]);

        return $desk;
    }
}
