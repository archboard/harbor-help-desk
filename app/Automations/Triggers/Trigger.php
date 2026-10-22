<?php

namespace App\Automations\Triggers;

use App\Automations\Context;
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

    /**
     * Placeholders only this trigger can fill in texts, as key => label.
     *
     * @return array<string, string>
     */
    public function placeholders(): array;

    /**
     * The values of {@see self::placeholders()} for this run, as key => value.
     *
     * @return array<string, string>
     */
    public function placeholderValues(Context $context): array;
}
