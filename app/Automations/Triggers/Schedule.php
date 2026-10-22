<?php

namespace App\Automations\Triggers;

use App\Automations\Context;
use App\Models\TicketEvent;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * A sweep over open tickets, hourly or daily. `time` is HH:MM in `timezone` (hourly sweeps only use
 * the minutes). A ticket fires once, or again after `refire_after_days` when that is set.
 * It never matches a ticket event: `automations:sweep` starts these.
 */
class Schedule implements Trigger
{
    public function key(): string
    {
        return 'schedule';
    }

    public function label(): string
    {
        return __('On a schedule');
    }

    public function configRules(): array
    {
        return [
            'frequency' => ['required', 'in:hourly,daily'],
            'time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', Rule::timezone()],
            'refire_after_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function fieldGroups(): array
    {
        return [];
    }

    public function matches(TicketEvent $event, array $config): bool
    {
        return false;
    }

    public function placeholders(): array
    {
        return [];
    }

    public function placeholderValues(Context $context): array
    {
        return [];
    }

    /**
     * The most recent scheduled moment at or before `$now`, computed in the config's timezone.
     *
     * @param  array<string, mixed>  $config
     */
    public static function latestMoment(array $config, CarbonImmutable $now): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', (string) $config['time']));
        $local = $now->setTimezone((string) $config['timezone']);

        if ($config['frequency'] === 'hourly') {
            $moment = $local->startOfHour()->minute($minute);

            return ($moment > $local ? $moment->subHour() : $moment)->utc();
        }

        $moment = $local->setTime($hour, $minute);

        return ($moment > $local ? $moment->subDay() : $moment)->utc();
    }
}
