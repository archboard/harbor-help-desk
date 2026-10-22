<?php

namespace App\Actions\Automations;

use App\Automations\EditorScope;
use App\Data\Automations\AutomationDefinitionData;
use App\Data\Automations\CreateAutomationData;
use App\Models\Automation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

class CreateAutomation
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): Automation
    {
        app(EditorScope::class)->set($actor, $input['desk_id'] ?? null);

        $data = CreateAutomationData::validateAndCreate($input);
        $definition = AutomationDefinitionData::from($data->definition);

        $automation = new Automation([
            'workspace_id' => Workspace::current()?->getKey(),
            'desk_id' => $data->deskId instanceof Optional ? null : $data->deskId,
            'name' => $data->name,
            'description' => $data->description instanceof Optional ? null : $data->description,
            'enabled' => $data->enabled instanceof Optional ? false : $data->enabled,
            'position' => self::nextPosition($definition->trigger->type),
            'trigger_type' => $definition->trigger->type,
            'definition' => $definition,
            'schema_version' => Automation::SCHEMA_VERSION,
            'last_swept_at' => now(),
            'created_by' => $actor->getKey(),
            'updated_by' => $actor->getKey(),
        ]);

        Gate::forUser($actor)->authorize('create', $automation);

        $automation->save();

        return $automation;
    }

    /**
     * The last position among the workspace's automations of the trigger type, plus one.
     */
    public static function nextPosition(string $triggerType): int
    {
        return (int) Automation::query()->where('trigger_type', $triggerType)->max('position') + 1;
    }
}
