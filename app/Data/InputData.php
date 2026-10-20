<?php

namespace App\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
     * Validate the input against this class's rules and `$contextRules`, the rules that need
     * something the class can't see (the model being updated, the actor), reporting every
     * error from both at once.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $contextRules
     */
    public static function validateAndCreateWith(array $input, array $contextRules): static
    {
        $context = Validator::make($input, $contextRules);

        try {
            $data = static::validateAndCreate($input);
        } catch (ValidationException $exception) {
            $context->passes();

            throw ValidationException::withMessages(array_merge_recursive($exception->errors(), $context->errors()->toArray()));
        }

        $context->validate();

        return $data;
    }

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
