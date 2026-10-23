<?php

namespace App\Actions\Automations;

use App\Models\Automation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DuplicateAutomation
{
    public function __construct(protected CreateAutomation $createAutomation) {}

    /**
     * A disabled copy in the same scope, placed last among the automations of its trigger.
     * Creating it goes through {@see CreateAutomation}, so the copy is validated and authorized like any new automation.
     */
    public function handle(User $actor, Automation $automation): Automation
    {
        Gate::forUser($actor)->authorize('view', $automation);

        return $this->createAutomation->handle($actor, [
            'name' => __(':name (copy)', ['name' => Str::limit($automation->name, 240, '')]),
            'description' => $automation->description,
            'desk_id' => $automation->desk_id,
            'enabled' => false,
            'definition' => $automation->definition->toArray(),
        ]);
    }
}
