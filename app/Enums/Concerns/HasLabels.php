<?php

namespace App\Enums\Concerns;

use BackedEnum;

/**
 * Human-readable, localized labels for a backed enum's cases. Each enum spells out its labels
 * as literal `__()` calls in `label()`, so translation tooling can find every string.
 *
 * @mixin BackedEnum
 */
trait HasLabels
{
    abstract public function label(): string;

    /**
     * Every case's label keyed by its value, e.g. for a select input.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
