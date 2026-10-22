<?php

namespace App\Automations\Triggers;

use App\Automations\FieldGroup;
use App\Models\TicketEvent;

/**
 * An occurrence that starts an automation, e.g. a customer reply.
 */
interface Trigger
{
    public function key(): string;

    public function label(): string;

    /**
     * The validation rules for the trigger's `config`.
     *
     * @return array<string, mixed>
     */
    public function configRules(): array;

    /**
     * The field groups the rules of an automation with this trigger can use.
     *
     * @return list<FieldGroup>
     */
    public function fieldGroups(): array;

    /**
     * Whether the event starts an automation with this config.
     *
     * @param  array<string, mixed>  $config
     */
    public function matches(TicketEvent $event, array $config): bool;
}
