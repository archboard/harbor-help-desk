<?php

namespace App\Automations\Actions;

use App\Automations\Context;

/**
 * Something an automation does to a ticket, always through the ticket Actions as the automation.
 */
interface AutomationAction
{
    public function key(): string;

    public function label(): string;

    /**
     * The validation rules for the step's `config`.
     *
     * @return array<string, mixed>
     */
    public function configRules(): array;

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
