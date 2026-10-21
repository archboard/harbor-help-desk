<?php

namespace App\Data\BusinessSchedules;

use App\Data\InputData;
use Closure;
use Spatie\LaravelData\Optional;

/**
 * `weekly_hours` maps `mon`..`sun` to lists of `["HH:MM", "HH:MM"]` intervals within one day.
 * Each starts before it ends (an end of "24:00" is midnight) and none overlap, with at least
 * one interval in the week. Any other keys of the week may be left out.
 */
class CreateBusinessScheduleData extends InputData
{
    public const array DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    private const string TIME = '/^(([01]\d|2[0-3]):[0-5]\d|24:00)$/';

    /**
     * @param  array<string, list<list<string>>>  $weeklyHours
     * @param  list<string>|Optional  $holidays
     */
    public function __construct(
        public string $name,
        public string $timezone,
        public array $weeklyHours,
        public array|Optional $holidays,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return self::rulesWith('required');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rulesWith(string $presence): array
    {
        return [
            'name' => [$presence, 'string', 'max:255'],
            'timezone' => [$presence, 'string', 'timezone:all'],
            'weekly_hours' => [$presence, 'array:'.implode(',', self::DAYS), self::hasHours(...)],
            'weekly_hours.*' => ['array', self::doesNotOverlap(...)],
            'weekly_hours.*.*' => ['bail', 'array', 'size:2', self::startsBeforeEnd(...)],
            'weekly_hours.*.*.*' => ['string', 'regex:'.self::TIME],
            'holidays' => ['sometimes', 'array'],
            'holidays.*' => ['date_format:Y-m-d'],
        ];
    }

    protected static function hasHours(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value) && array_filter($value) === []) {
            $fail(__('A schedule needs at least one interval of hours.'));
        }
    }

    protected static function startsBeforeEnd(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value) && count($value) === 2 && is_string($value[0] ?? null) && is_string($value[1] ?? null) && $value[0] >= $value[1]) {
            $fail(__('An interval must start before it ends.'));
        }
    }

    protected static function doesNotOverlap(string $attribute, mixed $value, Closure $fail): void
    {
        $intervals = array_values(array_filter(is_array($value) ? $value : [], fn (mixed $interval): bool => is_array($interval) && is_string($interval[0] ?? null) && is_string($interval[1] ?? null)));
        usort($intervals, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        foreach ($intervals as $i => $interval) {
            if ($i > 0 && $interval[0] < $intervals[$i - 1][1]) {
                $fail(__('Intervals within a day must not overlap.'));

                return;
            }
        }
    }
}
