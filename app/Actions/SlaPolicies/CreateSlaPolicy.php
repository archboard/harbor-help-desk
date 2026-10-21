<?php

namespace App\Actions\SlaPolicies;

use App\Data\SlaPolicies\CreateSlaPolicyData;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;

class CreateSlaPolicy
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): SlaPolicy
    {
        Gate::forUser($actor)->authorize('create', SlaPolicy::class);

        $workspaceId = Workspace::current()?->getKey();

        $data = CreateSlaPolicyData::validateAndCreateWith($input, [
            'business_schedule_id' => ['nullable', Rule::exists('business_schedules', 'id')->where('workspace_id', $workspaceId)],
        ]);

        return SlaPolicy::create([
            'workspace_id' => $workspaceId,
            'business_schedule_id' => $data->businessScheduleId instanceof Optional ? null : $data->businessScheduleId,
            'name' => $data->name,
            'targets' => $data->targets,
            'warning_percent' => $data->warningPercent instanceof Optional ? 75 : $data->warningPercent,
        ]);
    }
}
