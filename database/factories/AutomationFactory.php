<?php

namespace Database\Factories;

use App\Models\Automation;
use App\Models\Desk;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Workspace-wide and disabled by default, adding a note when a ticket is created.
 *
 * @extends Factory<Automation>
 */
class AutomationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => Workspace::current()?->getKey() ?? Workspace::factory(),
            'name' => fake()->words(3, true),
            'enabled' => false,
            'position' => 1,
            'trigger_type' => 'ticket.created',
            'definition' => $this->definitionFor('ticket.created', 'add_note', ['body' => 'Automated note']),
            'schema_version' => 1,
        ];
    }

    public function enabled(): static
    {
        return $this->state(['enabled' => true]);
    }

    public function forDesk(Desk $desk): static
    {
        return $this->state(['desk_id' => $desk->getKey()]);
    }

    /**
     * Run a single action step when the trigger fires.
     *
     * @param  array<string, mixed>  $config
     */
    public function triggeredBy(string $trigger, string $action, array $config = []): static
    {
        return $this->state([
            'trigger_type' => $trigger,
            'definition' => $this->definitionFor($trigger, $action, $config),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function definitionFor(string $trigger, string $action, array $config): array
    {
        return [
            'trigger' => ['type' => $trigger, 'config' => []],
            'steps' => [['id' => (string) Str::uuid(), 'type' => 'action', 'action' => $action, 'config' => $config]],
        ];
    }
}
