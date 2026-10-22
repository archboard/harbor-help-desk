<?php

namespace App\Automations\Fields;

use App\Automations\Context;
use App\Automations\FieldGroup;
use App\Automations\FieldType;
use App\Models\BusinessSchedule;

/**
 * A value a rule can compare, read from the ticket and the event that triggered the run.
 */
interface Field
{
    public function key(): string;

    public function label(): string;

    public function group(): FieldGroup;

    public function type(): FieldType;

    /**
     * The choices of an `enum` or `desk` field as value => label, else empty.
     *
     * @return array<string, string>
     */
    public function options(): array;

    /**
     * The current value, or null when there is none. Only `duration` fields use `$schedule`:
     * they count business minutes in it, else plain minutes.
     */
    public function resolve(Context $context, ?BusinessSchedule $schedule = null): mixed;
}
