<?php

namespace App\Data\Automations\Actions;

use Spatie\LaravelData\Data;

/**
 * Base for an action's `config`. Its rules validate the config when an automation is saved.
 */
abstract class ActionConfigData extends Data
{
    /**
     * @return array<string, array<int, mixed>>
     */
    abstract public static function rules(): array;
}
