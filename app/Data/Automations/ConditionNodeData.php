<?php

namespace App\Data\Automations;

use Spatie\LaravelData\Attributes\PropertyForMorph;
use Spatie\LaravelData\Contracts\PropertyMorphableData;
use Spatie\LaravelData\Data;

/**
 * A rule or a nested group of rules.
 *
 * @phpstan-sealed RuleData|ConditionGroupData
 */
abstract class ConditionNodeData extends Data implements PropertyMorphableData
{
    #[PropertyForMorph]
    public string $type;

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function morph(array $properties): ?string
    {
        return match ($properties['type'] ?? null) {
            'rule' => RuleData::class,
            'group' => ConditionGroupData::class,
            default => null,
        };
    }
}
