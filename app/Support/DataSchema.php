<?php

namespace App\Support;

use App\Data\InputData;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\JsonSchema\Types\StringType;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rules\Enum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Support\Validation\ValidationPath;
use Stringable;

/**
 * Describes an {@see InputData} class as JSON Schema properties, for MCP tools and OpenAPI.
 *
 * Types, nullability, enums and Optional come from the properties, `required`, `min`/`max`,
 * `in:` and `date`/`email` from `rules()`, and descriptions from the constructor's `@param` lines.
 */
class DataSchema
{
    /**
     * The properties of `$class` keyed by their input names, ready for a Tool's `schema()`.
     *
     * @param  class-string<InputData>  $class
     * @return array<string, Type>
     */
    public static function for(string $class): array
    {
        $rules = static::rules($class);
        $docs = static::docblock($class);
        $schema = [];

        foreach (app(DataConfig::class)->getDataClass($class)->properties as $property) {
            if ($property->computed || $property->isVirtual) {
                continue;
            }

            $key = $property->inputMappedName ?? $property->name;
            $schema[$key] = static::property($class, $property, $rules, $key, $docs[$property->name] ?? ['', '']);
        }

        return $schema;
    }

    /**
     * @param  class-string<InputData>  $class
     * @param  array<string, mixed>  $rules
     * @param  array{0: string, 1: string}  $doc  The `@param` type and description.
     */
    protected static function property(string $class, DataProperty $property, array $rules, string $key, array $doc): Type
    {
        $reflected = (new ReflectionProperty($class, $property->name))->getType();
        $names = array_map(
            fn (ReflectionType $type): string => $type instanceof ReflectionNamedType ? $type->getName() : 'mixed',
            $reflected instanceof ReflectionUnionType ? $reflected->getTypes() : array_filter([$reflected]),
        );
        $isOptional = in_array(Optional::class, $names, true);
        $names = array_values(array_diff($names, [Optional::class, 'null']));

        $ownRules = static::flatten($rules[$key] ?? []);
        $itemRules = static::flatten($rules["{$key}.*"] ?? []);
        $itemName = preg_match('/(?:list|array<int,\s*)<?\s*(\w+)/', $doc[0], $match) === 1 ? $match[1] : null;
        $scalarNames = array_values(array_diff($names, ['array']));

        if ($scalarNames !== [] && in_array('array', $names, true)) {
            $item = static::constrain(static::base($itemName ?? $scalarNames[0]), $itemRules ?: $ownRules);
            $type = JsonSchema::anyOf([static::constrain(static::base($scalarNames[0]), $ownRules), JsonSchema::array()->items($item)]);
        } else {
            $type = static::constrain(static::base($names[0] ?? 'mixed', $doc[0]), $ownRules);

            if ($type instanceof ArrayType && ($itemRules !== [] || $itemName !== null)) {
                $type->items(static::constrain(static::base($itemName ?? 'string'), $itemRules));
            }
        }

        if ($doc[1] !== '') {
            $type->description($doc[1]);
        }

        if ($reflected?->allowsNull() || in_array('nullable', $ownRules, true)) {
            $type->nullable();
        }

        $requiredByRules = in_array('required', $ownRules, true) && ! in_array('sometimes', $ownRules, true);

        if ($requiredByRules || (! $isOptional && ! $reflected?->allowsNull() && ! $property->hasDefaultValue)) {
            $type->required();
        }

        return $type;
    }

    /**
     * The JSON Schema type for a PHP type name.
     */
    protected static function base(string $name, string $docType = ''): Type
    {
        // ponytail: nested Data, maps and objects become a bare object, because ObjectType can't declare additionalProperties; give them typed properties if clients need them.
        return match (true) {
            $name === 'string' => JsonSchema::string(),
            $name === 'int' => JsonSchema::integer(),
            $name === 'float' => JsonSchema::number(),
            $name === 'bool' => JsonSchema::boolean(),
            $name === 'array' && str_contains($docType, 'array<string') => JsonSchema::object(),
            $name === 'array' => JsonSchema::array(),
            $name === 'mixed' => JsonSchema::union(['string', 'number', 'boolean', 'object', 'array']),
            is_subclass_of($name, BackedEnum::class) => JsonSchema::string()->enum($name),
            is_subclass_of($name, DateTimeInterface::class) => JsonSchema::string()->format('date-time'),
            $name === UploadedFile::class, $name === 'UploadedFile' => JsonSchema::string(),
            default => JsonSchema::object(),
        };
    }

    /**
     * Apply what the validation rules say about the value.
     *
     * @param  list<mixed>  $rules
     */
    protected static function constrain(Type $type, array $rules): Type
    {
        foreach ($rules as $rule) {
            if ($rule instanceof Enum) {
                $type->enum((new ReflectionProperty(Enum::class, 'type'))->getValue($rule));

                continue;
            }

            if (! is_string($rule) && ! $rule instanceof Stringable) {
                continue;
            }

            [$name, $arguments] = array_pad(explode(':', (string) $rule, 2), 2, '');

            if ($name === 'in') {
                $type->enum(str_getcsv($arguments, escape: '\\'));
            } elseif (($name === 'min' || $name === 'max') && is_numeric($arguments) && method_exists($type, $name)) {
                $type->{$name}($arguments + 0);
            } elseif ($type instanceof StringType && $name === 'email') {
                $type->format('email');
            } elseif ($type instanceof StringType && $name === 'date') {
                $type->format('date-time');
            }
        }

        return $type;
    }

    /**
     * The class's rules.
     *
     * @param  class-string<InputData>  $class
     * @return array<string, mixed>
     */
    protected static function rules(string $class): array
    {
        if (! method_exists($class, 'rules')) {
            return [];
        }

        // ponytail: rules that depend on the payload see an empty one, so a list filter maps from its single-value rules.
        return (new ReflectionClass($class))->getMethod('rules')->getNumberOfParameters() > 0
            ? $class::rules(new ValidationContext([], [], ValidationPath::create()))
            : $class::rules();
    }

    /**
     * @return list<mixed>
     */
    protected static function flatten(mixed $rules): array
    {
        return array_values(is_string($rules) ? explode('|', $rules) : (array) $rules);
    }

    /**
     * The `@param` type and description of each constructor parameter, keyed by name.
     *
     * @param  class-string<InputData>  $class
     * @return array<string, array{0: string, 1: string}>
     */
    protected static function docblock(string $class): array
    {
        $doc = (new ReflectionClass($class))->getConstructor()?->getDocComment() ?: '';
        preg_match_all('/@param\s+(.+?)\s+\$(\w+)[ \t]*(.*)$/m', $doc, $matches, PREG_SET_ORDER);

        $params = [];

        foreach ($matches as $match) {
            $params[$match[2]] = [$match[1], trim($match[3])];
        }

        return $params;
    }
}
