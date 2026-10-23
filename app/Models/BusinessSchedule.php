<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\BusinessScheduleFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * The hours a workspace is open, which SLA targets are measured in.
 *
 * `weekly_hours` maps `mon`..`sun` to a list of `[start, end]` "HH:MM" intervals within one day
 * (split shifts allowed, an end of "24:00" means midnight). Overnight hours are two intervals,
 * e.g. mon `["20:00","24:00"]` and tue `["00:00","04:00"]`. `holidays` are "Y-m-d" dates in
 * the schedule's timezone on which no time counts.
 *
 * @property-read Workspace|null $workspace
 *
 * @method static \Database\Factories\BusinessScheduleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSchedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSchedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSchedule query()
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $name
 * @property string $timezone
 * @property array<array-key, mixed> $weekly_hours
 * @property array<array-key, mixed> $holidays
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSchedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSchedule whereHolidays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSchedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSchedule whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSchedule whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSchedule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSchedule whereWeeklyHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessSchedule whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class BusinessSchedule extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<BusinessScheduleFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids;

    private const int MAX_DAYS = 732;

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekly_hours' => 'array',
            'holidays' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'bsc_';
    }

    /**
     * The moment after `$minutes` of business time has passed since `$from`, or before it for a negative number. A start outside
     * hours begins counting at the next opening.
     */
    public function addBusinessMinutes(CarbonInterface $from, int $minutes): CarbonImmutable
    {
        $cursor = CarbonImmutable::instance($from)->setTimezone($this->timezone);

        if ($minutes < 0) {
            return $this->subtractBusinessSeconds($cursor, -$minutes * 60);
        }

        $remaining = $minutes * 60;

        for ($day = $cursor->startOfDay(), $i = 0; $i < self::MAX_DAYS; $day = $day->addDay()->startOfDay(), $i++) {
            foreach ($this->intervalsOn($day) as [$start, $end]) {
                if ($end <= $cursor) {
                    continue;
                }

                $start = $start->max($cursor);
                $available = $end->getTimestamp() - $start->getTimestamp();

                if ($remaining <= $available) {
                    return $start->addSeconds($remaining);
                }

                $remaining -= $available;
            }
        }

        throw new RuntimeException('The business schedule has no business hours within two years.');
    }

    /**
     * The moment `$remaining` seconds of business time before `$cursor`. A start outside hours counts back from the previous closing.
     */
    private function subtractBusinessSeconds(CarbonImmutable $cursor, int $remaining): CarbonImmutable
    {
        for ($day = $cursor->startOfDay(), $i = 0; $i < self::MAX_DAYS; $day = $day->subDay()->startOfDay(), $i++) {
            foreach (array_reverse($this->intervalsOn($day)) as [$start, $end]) {
                if ($start >= $cursor) {
                    continue;
                }

                $end = $end->min($cursor);
                $available = $end->getTimestamp() - $start->getTimestamp();

                if ($remaining <= $available) {
                    return $end->subSeconds($remaining);
                }

                $remaining -= $available;
            }
        }

        throw new RuntimeException('The business schedule has no business hours within two years.');
    }

    /**
     * Whole business minutes between two moments, zero when `$to` isn't after `$from`.
     */
    public function businessMinutesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $from = CarbonImmutable::instance($from)->setTimezone($this->timezone);
        $to = CarbonImmutable::instance($to)->setTimezone($this->timezone);
        $seconds = 0;

        for ($day = $from->startOfDay(); $day <= $to; $day = $day->addDay()->startOfDay()) {
            foreach ($this->intervalsOn($day) as [$start, $end]) {
                $overlap = min($end->getTimestamp(), $to->getTimestamp()) - max($start->getTimestamp(), $from->getTimestamp());
                $seconds += max(0, $overlap);
            }
        }

        return intdiv($seconds, 60);
    }

    /**
     * A null schedule is 24/7, so callers needn't branch.
     */
    public static function addMinutesFor(?self $schedule, CarbonInterface $from, int $minutes): CarbonImmutable
    {
        return $schedule?->addBusinessMinutes($from, $minutes) ?? CarbonImmutable::instance($from)->addMinutes($minutes);
    }

    public static function minutesBetweenFor(?self $schedule, CarbonInterface $from, CarbonInterface $to): int
    {
        return $schedule?->businessMinutesBetween($from, $to) ?? max(0, (int) $from->diffInMinutes($to, false));
    }

    /**
     * The open intervals of a day in the schedule's timezone, built from wall-clock times so DST is right.
     *
     * @return list<array{CarbonImmutable, CarbonImmutable}>
     */
    private function intervalsOn(CarbonImmutable $day): array
    {
        $date = $day->format('Y-m-d');

        if (in_array($date, $this->holidays ?? [], true)) {
            return [];
        }

        $intervals = $this->weekly_hours[strtolower($day->format('D'))] ?? [];
        sort($intervals);

        return array_map(
            fn (array $interval): array => [
                CarbonImmutable::parse("$date $interval[0]", $this->timezone),
                CarbonImmutable::parse("$date $interval[1]", $this->timezone),
            ],
            $intervals,
        );
    }
}
