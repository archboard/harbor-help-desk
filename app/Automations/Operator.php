<?php

namespace App\Automations;

use App\Enums\Concerns\HasLabels;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;

/**
 * How a rule compares a field's value with the rule's. Text comparisons ignore case.
 *
 * `apply()` takes the field's resolved value and the rule's value. The `changed*` operators
 * instead take the change the triggering event made to that field, `['from' => …, 'to' => …]`,
 * or null when the event didn't touch it ({@see Context::change()}). `changed_to` and
 * `changed_from` compare the rule's value with that pair's side.
 */
enum Operator: string
{
    use HasLabels;

    /** Backtracks a regex may spend on one value, so a hostile pattern can't stall a worker. */
    private const string BACKTRACK_LIMIT = '10000';

    case IS = 'is';
    case IS_NOT = 'is_not';
    case IN = 'in';
    case NOT_IN = 'not_in';
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'not_contains';
    case STARTS_WITH = 'starts_with';
    case ENDS_WITH = 'ends_with';
    case MATCHES = 'matches';
    case IS_EMPTY = 'is_empty';
    case IS_NOT_EMPTY = 'is_not_empty';
    case GT = 'gt';
    case GTE = 'gte';
    case LT = 'lt';
    case LTE = 'lte';
    case INCLUDES_ANY = 'includes_any';
    case INCLUDES_ALL = 'includes_all';
    case EXCLUDES = 'excludes';
    case CHANGED = 'changed';
    case CHANGED_TO = 'changed_to';
    case CHANGED_FROM = 'changed_from';

    public function label(?FieldType $type = null): string
    {
        if ($type === FieldType::DATETIME) {
            return match ($this) {
                self::GT => __('is after'),
                self::GTE => __('is on or after'),
                self::LT => __('is before'),
                self::LTE => __('is on or before'),
                default => $this->label(),
            };
        }

        return match ($this) {
            self::IS => __('is'),
            self::IS_NOT => __('is not'),
            self::IN => __('is one of'),
            self::NOT_IN => __('is not one of'),
            self::CONTAINS => __('contains'),
            self::NOT_CONTAINS => __('does not contain'),
            self::STARTS_WITH => __('starts with'),
            self::ENDS_WITH => __('ends with'),
            self::MATCHES => __('matches pattern'),
            self::IS_EMPTY => __('is empty'),
            self::IS_NOT_EMPTY => __('is not empty'),
            self::GT => __('is greater than'),
            self::GTE => __('is at least'),
            self::LT => __('is less than'),
            self::LTE => __('is at most'),
            self::INCLUDES_ANY => __('includes any of'),
            self::INCLUDES_ALL => __('includes all of'),
            self::EXCLUDES => __('includes none of'),
            self::CHANGED => __('changed'),
            self::CHANGED_TO => __('changed to'),
            self::CHANGED_FROM => __('changed from'),
        };
    }

    /**
     * The field types the operator can compare.
     *
     * @return list<FieldType>
     */
    public function fieldTypes(): array
    {
        return match ($this) {
            self::IS, self::IS_NOT => [FieldType::STRING, FieldType::ENUM, FieldType::USER, FieldType::DESK, FieldType::NUMBER, FieldType::BOOLEAN],
            self::IN, self::NOT_IN => [FieldType::STRING, FieldType::ENUM, FieldType::USER, FieldType::DESK],
            self::CONTAINS, self::NOT_CONTAINS, self::STARTS_WITH, self::ENDS_WITH, self::MATCHES => [FieldType::STRING, FieldType::TEXT],
            self::IS_EMPTY, self::IS_NOT_EMPTY => [FieldType::STRING, FieldType::TEXT, FieldType::ENUM, FieldType::MULTI_ENUM, FieldType::USER, FieldType::USERS, FieldType::NUMBER, FieldType::DATETIME, FieldType::DURATION],
            self::GT, self::GTE, self::LT, self::LTE => [FieldType::NUMBER, FieldType::DATETIME, FieldType::DURATION],
            self::INCLUDES_ANY, self::INCLUDES_ALL, self::EXCLUDES => [FieldType::MULTI_ENUM, FieldType::USERS],
            self::CHANGED, self::CHANGED_TO, self::CHANGED_FROM => [FieldType::STRING, FieldType::ENUM, FieldType::MULTI_ENUM, FieldType::USER, FieldType::USERS, FieldType::DESK, FieldType::NUMBER, FieldType::BOOLEAN, FieldType::DATETIME],
        };
    }

    public function supports(FieldType $type): bool
    {
        return in_array($type, $this->fieldTypes(), true);
    }

    /**
     * Whether the operator reads the triggering event's change instead of the field's value.
     */
    public function readsChange(): bool
    {
        return in_array($this, [self::CHANGED, self::CHANGED_TO, self::CHANGED_FROM], true);
    }

    /**
     * Whether the rule needs a value to compare with.
     */
    public function takesValue(): bool
    {
        return ! in_array($this, [self::IS_EMPTY, self::IS_NOT_EMPTY, self::CHANGED], true);
    }

    public function apply(mixed $actual, mixed $expected = null): bool
    {
        return match ($this) {
            self::IS => self::same($actual, $expected),
            self::IS_NOT => ! self::same($actual, $expected),
            self::IN => self::inList($actual, $expected),
            self::NOT_IN => ! self::inList($actual, $expected),
            self::CONTAINS => self::textTest($actual, $expected, str_contains(...)),
            self::NOT_CONTAINS => ! self::textTest($actual, $expected, str_contains(...)),
            self::STARTS_WITH => self::textTest($actual, $expected, str_starts_with(...)),
            self::ENDS_WITH => self::textTest($actual, $expected, str_ends_with(...)),
            self::MATCHES => self::matchesPattern($actual, $expected),
            self::IS_EMPTY => self::isEmpty($actual),
            self::IS_NOT_EMPTY => ! self::isEmpty($actual),
            self::GT => (self::compare($actual, $expected) ?? 0) > 0,
            self::GTE => (self::compare($actual, $expected) ?? -1) >= 0,
            self::LT => (self::compare($actual, $expected) ?? 0) < 0,
            self::LTE => (self::compare($actual, $expected) ?? 1) <= 0,
            self::INCLUDES_ANY => array_intersect(self::list($actual), self::list($expected)) !== [],
            self::INCLUDES_ALL => self::list($expected) !== [] && array_diff(self::list($expected), self::list($actual)) === [],
            self::EXCLUDES => array_intersect(self::list($actual), self::list($expected)) === [],
            self::CHANGED => is_array($actual),
            self::CHANGED_TO => is_array($actual) && self::same($actual['to'] ?? null, $expected),
            self::CHANGED_FROM => is_array($actual) && self::same($actual['from'] ?? null, $expected),
        };
    }

    /**
     * Whether `$pattern` compiles, for rejecting a bad regex when an automation is saved.
     */
    public static function isValidPattern(mixed $pattern): bool
    {
        return is_string($pattern) && @preg_match(self::delimit($pattern), '') !== false;
    }

    private static function delimit(string $pattern): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~iu';
    }

    private static function matchesPattern(mixed $actual, mixed $pattern): bool
    {
        if (! is_scalar($actual) || ! is_string($pattern)) {
            return false;
        }

        $previous = ini_set('pcre.backtrack_limit', self::BACKTRACK_LIMIT);

        try {
            return @preg_match(self::delimit($pattern), (string) $actual) === 1;
        } finally {
            ini_set('pcre.backtrack_limit', (string) $previous);
        }
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) ? mb_strtolower((string) $value) : null;
    }

    /**
     * @return list<string>
     */
    private static function list(mixed $value): array
    {
        return array_values(array_filter(array_map(self::text(...), Arr::wrap($value)), fn (?string $item): bool => $item !== null));
    }

    private static function same(mixed $actual, mixed $expected): bool
    {
        if (is_bool($actual)) {
            return $actual === filter_var($expected, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        $actual = self::text($actual);

        return $actual !== null && $actual === self::text($expected);
    }

    private static function inList(mixed $actual, mixed $expected): bool
    {
        return array_any(Arr::wrap($expected), fn (mixed $item): bool => self::same($actual, $item));
    }

    /**
     * @param  callable(string, string): bool  $test
     */
    private static function textTest(mixed $actual, mixed $expected, callable $test): bool
    {
        $actual = self::text($actual);
        $expected = self::text($expected);

        return $actual !== null && $expected !== null && $expected !== '' && $test($actual, $expected);
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private static function compare(mixed $actual, mixed $expected): ?int
    {
        if ($actual === null || $expected === null) {
            return null;
        }

        if ($actual instanceof CarbonInterface) {
            return $actual->getTimestamp() <=> CarbonImmutable::parse($expected)->getTimestamp();
        }

        return is_numeric($actual) && is_numeric($expected) ? (float) $actual <=> (float) $expected : null;
    }
}
