<?php

namespace App\Actions\SlaPolicies;

use App\Data\SlaPolicies\UpdateSlaPolicyData;
use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateSlaPolicy
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, SlaPolicy $slaPolicy, array $input): SlaPolicy
    {
        Gate::forUser($actor)->authorize('update', $slaPolicy);

        $data = UpdateSlaPolicyData::validateAndCreateWith($input, [
            'business_schedule_id' => ['nullable', Rule::exists('business_schedules', 'id')->where('workspace_id', $slaPolicy->workspace_id)],
        ]);

        $slaPolicy->update($data->filled());

        return $slaPolicy;
    }
}
