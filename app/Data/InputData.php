<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Base for the Data classes that Actions validate their input with.
 */
abstract class InputData extends Data
{
    /**
     * The properties the input provided, keyed by their snake_case names, for `fill()` and
     * `update()`. Values stay as cast (enums, dates), and Optional properties are left out.
     *
     * @return array<string, mixed>
     */
    public function filled(): array
    {
        return app(DataConfig::class)->getDataClass(static::class)->properties
            ->reject(fn (DataProperty $property): bool => $this->{$property->name} instanceof Optional)
            ->mapWithKeys(fn (DataProperty $property): array => [
                $property->outputMappedName ?? $property->name => $this->{$property->name},
            ])
            ->all();
    }
}
