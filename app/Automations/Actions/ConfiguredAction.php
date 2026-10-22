<?php

namespace App\Automations\Actions;

use App\Automations\AutomationException;
use App\Automations\AutomationRegistry;
use App\Automations\Context;
use App\Automations\PlaceholderRenderer;
use App\Data\Automations\DelayForData;
use App\Models\Automation;
use App\Models\BusinessSchedule;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Str;

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

    /**
     * Fill in the placeholders of a text for the run's ticket, with the triggering event's own
     * (`{{ message.body }}`, ...), as the automation. Cut to `$limit` characters.
     */
    protected function render(Context $context, string $text, int $limit): string
    {
        $trigger = app(AutomationRegistry::class)->trigger($context->run->definition->trigger->type);

        return Str::limit(
            app(PlaceholderRenderer::class)->render($text, $context->ticket, $this->actor($context), $trigger?->placeholderValues($context) ?? []),
            $limit,
            '',
        );
    }

    /**
     * A save-time rule for a user id: a workspace member, and a member of the desk when scoped to one.
     */
    protected function memberRule(?Desk $scope): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($scope): void {
            $user = is_string($value) ? Workspace::current()?->users()->whereKey($value)->first() : null;

            if ($user === null) {
                $fail(__('This user is not a member of the workspace.'));
            } elseif ($scope !== null && ! $scope->users()->whereKey($user->getKey())->exists()) {
                $fail(__('This user is not a member of the automation’s desk.'));
            }
        };
    }
}
