<?php

namespace App\Actions\Automations;

use App\Automations\DefinitionValidator;
use App\Automations\EditorScope;
use App\Data\Automations\AutomationDefinitionData;
use App\Data\Automations\UpdateAutomationData;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

class UpdateAutomation
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Automation $automation, array $input): Automation
    {
        Gate::forUser($actor)->authorize('update', $automation);

        app(EditorScope::class)->set($actor, array_key_exists('desk_id', $input) ? $input['desk_id'] : $automation->desk_id);

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
                'last_swept_at' => now(),
            ]);
        }

        if ($automation->isDirty('desk_id')) {
            $automation->unsetRelation('desk');

            Gate::forUser($actor)->authorize('update', $automation);

            if ($data->definition instanceof Optional) {
                $this->validateStoredDefinition($automation);
            }
        }

        $automation->updated_by = $actor->getKey();
        $automation->save();

        return $automation;
    }

    /**
     * A new desk changes what the actions may reference, so the stored definition is checked
     * again against it.
     *
     * @throws ValidationException
     */
    protected function validateStoredDefinition(Automation $automation): void
    {
        $errors = collect(app(DefinitionValidator::class)->errors($automation->definition->toArray()))
            ->mapWithKeys(fn (array $messages, string $key): array => ["definition.$key" => $messages])
            ->all();

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
