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

        // An automation switched on starts from now, so it doesn't fire for a moment that passed while it was off.
        $automation->update([...self::switchAttributes($automation, $data->enabled), 'updated_by' => $actor->getKey()]);

        return $automation;
    }

    /**
     * What switching the automation on or off changes. Shared with {@see UpdateAutomation} so both paths agree.
     *
     * @return array<string, mixed>
     */
    public static function switchAttributes(Automation $automation, bool $enabled): array
    {
        return [
            'enabled' => $enabled,
            ...($enabled && ! $automation->enabled ? ['last_swept_at' => now()] : []),
        ];
    }
}
