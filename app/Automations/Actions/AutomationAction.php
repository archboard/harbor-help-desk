<?php

namespace App\Automations\Actions;

use App\Automations\Context;
use App\Data\Automations\Actions\ActionConfigData;
use App\Models\Desk;
use App\Models\User;

/**
 * Something an automation does to a ticket, always through the ticket Actions as the automation.
 */
interface AutomationAction
{
    public function key(): string;

    public function label(): string;

    /**
     * The Data class describing the step's `config`. The builder gets its generated TypeScript type.
     *
     * @return class-string<ActionConfigData>
     */
    public function configData(): string;

    /**
     * The validation rules for the step's `config`, from {@see self::configData()}.
     *
     * @return array<string, mixed>
     */
    public function configRules(): array;

    /**
     * Extra rules for saving: what the config may reference depends on who saves the automation
     * (`$editor`, null when unknown) and the desk it is scoped to (`$scope`, null when workspace-wide).
     * Automations bypass policies when they run, so this is the only place they are enforced.
     *
     * @return array<string, mixed>
     */
    public function contextRules(?User $editor, ?Desk $scope): array;

    /**
     * Do it. The returned text is logged on the run.
     *
     * @param  array<string, mixed>  $config
     */
    public function execute(Context $context, array $config): string;

    /**
     * What `execute()` would do, for test runs. Changes nothing.
     *
     * @param  array<string, mixed>  $config
     */
    public function describe(Context $context, array $config): string;
}
