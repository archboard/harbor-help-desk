<?php

namespace App\Actions\Automations;

use App\Data\Automations\AutomationDefinitionData;
use App\Data\Automations\UpdateAutomationData;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

class UpdateAutomation
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Automation $automation, array $input): Automation
    {
        Gate::forUser($actor)->authorize('update', $automation);

        $data = UpdateAutomationData::validateAndCreate($input);

        $automation->fill(Arr::only($data->filled(), ['name', 'description', 'desk_id']));

        if (! $data->definition instanceof Optional) {
            $definition = AutomationDefinitionData::from($data->definition);

            if ($definition->trigger->type !== $automation->trigger_type) {
                $automation->position = CreateAutomation::nextPosition($definition->trigger->type);
            }

            $automation->fill([
                'trigger_type' => $definition->trigger->type,
                'definition' => $definition,
                'schema_version' => Automation::SCHEMA_VERSION,
            ]);
        }

        if ($automation->isDirty('desk_id')) {
            $automation->unsetRelation('desk');

            Gate::forUser($actor)->authorize('update', $automation);
        }

        $automation->updated_by = $actor->getKey();
        $automation->save();

        return $automation;
    }
}
