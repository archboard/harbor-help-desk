<?php

namespace App\Actions\Automations;

use App\Data\Automations\SetAutomationEnabledData;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SetAutomationEnabled
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Automation $automation, array $input): Automation
    {
        Gate::forUser($actor)->authorize('update', $automation);

        $data = SetAutomationEnabledData::validateAndCreate($input);

        $automation->update(['enabled' => $data->enabled, 'updated_by' => $actor->getKey()]);

        return $automation;
    }
}
