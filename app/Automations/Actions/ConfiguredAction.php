<?php

namespace App\Automations\Actions;

use App\Automations\AutomationException;
use App\Automations\Context;
use App\Data\Automations\DelayForData;
use App\Models\Automation;
use App\Models\BusinessSchedule;
use App\Models\Desk;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Base for actions whose config rules come from their config Data class.
 */
abstract class ConfiguredAction implements AutomationAction
{
    public function configRules(): array
    {
        return $this->configData()::rules();
    }

    public function contextRules(?User $editor, ?Desk $scope): array
    {
        return [];
    }

    protected function actor(Context $context): Automation
    {
        return $context->run->automation;
    }

    /**
     * The moment an interval from now ends, in business time when it names a schedule.
     */
    protected function after(DelayForData $interval): CarbonImmutable
    {
        $schedule = null;

        if ($interval->businessScheduleId !== null) {
            $schedule = BusinessSchedule::query()->find($interval->businessScheduleId)
                ?? throw new AutomationException(__('The business schedule no longer exists.'));
        }

        return BusinessSchedule::addMinutesFor($schedule, CarbonImmutable::now(), $interval->unit->toMinutes($interval->amount));
    }
}
