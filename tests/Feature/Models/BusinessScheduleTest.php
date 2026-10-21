<?php

use App\Models\BusinessSchedule;
use Carbon\CarbonImmutable;

/**
 * @param  array<string, mixed>  $attributes
 */
function schedule(array $attributes = []): BusinessSchedule
{
    return BusinessSchedule::factory()->make($attributes);
}

function utc(string $time): CarbonImmutable
{
    return CarbonImmutable::parse($time, 'UTC');
}

it('starts counting at opening when the start is before hours', function () {
    // Wed 07:00 -> opens 09:00, +60 = 10:00
    expect(schedule()->addBusinessMinutes(utc('2026-10-21 07:00'), 60)->toDateTimeString())->toBe('2026-10-21 10:00:00');
});

it('rolls over to the next business day after hours', function () {
    // Wed 18:00 -> Thu 09:00, +30 = 09:30
    expect(schedule()->addBusinessMinutes(utc('2026-10-21 18:00'), 30)->toDateTimeString())->toBe('2026-10-22 09:30:00');
});

it('skips the weekend', function () {
    // Fri 16:30: 30 min Friday, 60 min Monday 09:00 -> 10:00
    expect(schedule()->addBusinessMinutes(utc('2026-10-23 16:30'), 90)->toDateTimeString())->toBe('2026-10-26 10:00:00');
});

it('counts overnight hours split across two days', function () {
    $overnight = schedule(['weekly_hours' => ['mon' => [['20:00', '24:00']], 'tue' => [['00:00', '04:00']]]]);

    // Mon 23:00: 60 min Monday, 60 min Tuesday from 00:00 -> 01:00
    expect($overnight->addBusinessMinutes(utc('2026-10-26 23:00'), 120)->toDateTimeString())->toBe('2026-10-27 01:00:00');
    // Mon 20:00 to Tue 04:00 is 4 + 4 hours
    expect($overnight->businessMinutesBetween(utc('2026-10-26 20:00'), utc('2026-10-27 04:00')))->toBe(480);
});

it('skips holidays', function () {
    $holiday = schedule(['holidays' => ['2026-10-22']]);

    // Wed 16:00: 60 min Wednesday, Thursday is a holiday, 60 min Friday 09:00 -> 10:00
    expect($holiday->addBusinessMinutes(utc('2026-10-21 16:00'), 120)->toDateTimeString())->toBe('2026-10-23 10:00:00');
    // Wed 09:00 to Fri 17:00 without Thursday is 8h + 8h
    expect($holiday->businessMinutesBetween(utc('2026-10-21 09:00'), utc('2026-10-23 17:00')))->toBe(960);
});

it('handles split shifts', function () {
    $split = schedule(['weekly_hours' => ['mon' => [['09:00', '12:00'], ['13:00', '17:00']]]]);

    // Mon 11:30: 30 min before lunch, 30 min after -> 13:30
    expect($split->addBusinessMinutes(utc('2026-10-26 11:30'), 60)->toDateTimeString())->toBe('2026-10-26 13:30:00');
    expect($split->businessMinutesBetween(utc('2026-10-26 08:00'), utc('2026-10-26 18:00')))->toBe(420);
});

it('measures wall-clock hours across the spring-forward change', function () {
    // New York, Sun 2026-03-08: 02:00 jumps to 03:00, so 01:00-04:00 is only 2 real hours
    $newYork = schedule(['timezone' => 'America/New_York', 'weekly_hours' => ['sun' => [['01:00', '04:00']]]]);
    $start = CarbonImmutable::parse('2026-03-08 01:00', 'America/New_York');

    expect($newYork->businessMinutesBetween($start, CarbonImmutable::parse('2026-03-08 04:00', 'America/New_York')))->toBe(120);
    // 120 minutes ends exactly at 04:00 EDT (08:00 UTC); 121 spills to the next Sunday
    expect($newYork->addBusinessMinutes($start, 120)->utc()->toDateTimeString())->toBe('2026-03-08 08:00:00');
    expect($newYork->addBusinessMinutes($start, 121)->toDateString())->toBe('2026-03-15');
});

it('measures wall-clock hours across the fall-back change', function () {
    // New York, Sun 2026-11-01: 01:00-02:00 happens twice, so 00:00-03:00 is 4 real hours
    $newYork = schedule(['timezone' => 'America/New_York', 'weekly_hours' => ['sun' => [['00:00', '03:00']]]]);
    $start = CarbonImmutable::parse('2026-11-01 00:00', 'America/New_York');

    expect($newYork->businessMinutesBetween($start, CarbonImmutable::parse('2026-11-01 03:00', 'America/New_York')))->toBe(240);
    // 240 minutes ends at 03:00 EST (08:00 UTC)
    expect($newYork->addBusinessMinutes($start, 240)->utc()->toDateTimeString())->toBe('2026-11-01 08:00:00');
});

it('round-trips between adding and measuring', function () {
    $start = utc('2026-10-23 15:00');
    $end = schedule()->addBusinessMinutes($start, 600);

    // Fri 15:00-17:00 is 120, Mon 09:00-17:00 is 480
    expect($end->toDateTimeString())->toBe('2026-10-26 17:00:00')
        ->and(schedule()->businessMinutesBetween($start, $end))->toBe(600);
});

it('returns zero business minutes when the range is backwards or entirely outside hours', function () {
    expect(schedule()->businessMinutesBetween(utc('2026-10-22 12:00'), utc('2026-10-22 11:00')))->toBe(0)
        ->and(schedule()->businessMinutesBetween(utc('2026-10-24 09:00'), utc('2026-10-25 17:00')))->toBe(0);
});

it('throws when the schedule has no hours', function () {
    schedule(['weekly_hours' => []])->addBusinessMinutes(utc('2026-10-21 09:00'), 60);
})->throws(RuntimeException::class);

it('treats a missing schedule as 24/7', function () {
    $from = utc('2026-10-24 03:00');

    expect(BusinessSchedule::addMinutesFor(null, $from, 90)->toDateTimeString())->toBe('2026-10-24 04:30:00')
        ->and(BusinessSchedule::minutesBetweenFor(null, $from, utc('2026-10-25 03:00')))->toBe(1440)
        ->and(BusinessSchedule::minutesBetweenFor(null, utc('2026-10-25 03:00'), $from))->toBe(0)
        ->and(BusinessSchedule::addMinutesFor(schedule(), $from, 60)->toDateTimeString())->toBe('2026-10-26 10:00:00')
        ->and(BusinessSchedule::minutesBetweenFor(schedule(), utc('2026-10-26 09:00'), utc('2026-10-26 10:00')))->toBe(60);
});
