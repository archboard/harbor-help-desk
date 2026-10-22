<?php

namespace App\Automations;

use App\Data\Automations\ActionStepData;
use App\Data\Automations\AutomationDefinitionData;
use App\Data\Automations\ConditionStepData;
use App\Data\Automations\DelayStepData;
use App\Data\Automations\PathsStepData;
use App\Data\Automations\StepData;
use App\Data\Automations\StopStepData;
use App\Enums\AutomationRunStatus;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\BusinessSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/**
 * Walks the steps of a run's definition snapshot and records one log entry per step.
 *
 * - A condition that fails ends the run as `completed` with `state.matched = false`.
 * - An action runs as the automation, and the events it causes carry the run's id ({@see RunCausation}).
 * - Paths run the first branch whose condition passes, else the `else` steps.
 * - A delay pauses the run as `waiting` until a time ({@see self::delay()}); {@see self::resume()} continues it.
 * - A stop ends the run and sets `state.stopped`, which makes the caller skip later automations.
 * - Anything thrown by a step fails the run with the error stored, and tells the automation's creator
 *   ({@see NotifyCreatorOfFailure}). It never reaches the caller.
 *
 * Holds no state between runs: everything lives in the run and the context.
 */
class Engine
{
    public function __construct(
        protected AutomationRegistry $registry,
        protected ConditionEvaluator $conditions,
        protected RunCausation $causation,
        protected NotifyCreatorOfFailure $notifyCreator,
    ) {}

    public function run(Automation $automation, Context $context, AutomationRun $run): AutomationRun
    {
        return $this->execute($automation, $context, $run, []);
    }

    /**
     * Continue a waiting run from the snapshot it ran with, at the pointer its delay stored in
     * `state.resume`. The context is built fresh, so conditions see the ticket as it is now. The
     * run keeps its depth: resuming is not a new trigger, so it takes no rate-limit attempt.
     */
    public function resume(Automation $automation, Context $context, AutomationRun $run): AutomationRun
    {
        $pointer = $run->state['resume'] ?? [];
        $run->state = Arr::except($run->state, ['resume', 'cancel_on']);
        $run->resume_at = null;

        return $this->execute($automation, $context, $run, $pointer);
    }

    /**
     * Walk the definition like a real run, but describe what each action would do instead of doing it,
     * and continue past delays. Nothing is saved, dispatched, rate limited or stamped: the run in the
     * context is an unsaved stand-in that only collects the log. Step errors are captured like in a real run.
     *
     * @return array{matched: bool, stopped: bool, log: array<int, array<string, mixed>>, error: string|null}
     */
    public function dryRun(AutomationDefinitionData $definition, Context $context): array
    {
        $run = $context->run;
        $error = null;

        if (! $context->dryRun || $run === null) {
            throw new LogicException('A dry run needs a context made by Context::forDryRun().');
        }

        try {
            $this->steps($definition->steps, $context, $run);
        } catch (Throwable $exception) {
            $error = $this->message($exception);
        }

        return [
            'matched' => ($run->state['matched'] ?? true) === true,
            'stopped' => ($run->state['stopped'] ?? false) === true,
            'log' => $run->log,
            'error' => $error,
        ];
    }

    /**
     * @param  list<int|string>  $resume
     */
    protected function execute(Automation $automation, Context $context, AutomationRun $run, array $resume): AutomationRun
    {
        $run->status = AutomationRunStatus::RUNNING;

        try {
            $finished = $this->steps($run->definition->steps, $context, $run, [], $resume);
            $run->status = $run->resume_at !== null && ! $finished ? AutomationRunStatus::WAITING : AutomationRunStatus::COMPLETED;
        } catch (Throwable $exception) {
            $run->status = AutomationRunStatus::FAILED;
            $run->error = $this->message($exception);

            $logContext = ['automation_id' => $automation->getKey(), 'run_id' => $run->getKey(), 'error' => $run->error];
            $this->isSafe($exception)
                ? Log::warning('Automation step failed.', $logContext)
                : report($exception);
        }

        $run->save();

        if ($run->status === AutomationRunStatus::FAILED) {
            $this->notifyCreator->handle($automation, $run);
        }

        try {
            $context->runDeferred();
        } catch (AutomationException) {
            // A deferred job (on the sync queue) has recorded its own failure on the run.
        } catch (Throwable $exception) {
            report($exception);
        }

        return $run;
    }

    /**
     * `$prefix` locates `$steps` in the definition and `$resume` is the rest of a pointer; see {@see self::delay()}.
     *
     * @param  array<int, StepData>  $steps
     * @param  list<int|string>  $prefix
     * @param  list<int|string>  $resume
     * @return bool False when the run has ended or is waiting: a condition failed, a stop was reached or a delay paused it.
     */
    protected function steps(array $steps, Context $context, AutomationRun $run, array $prefix = [], array $resume = []): bool
    {
        $start = (int) ($resume === [] ? 0 : array_shift($resume));

        if ($resume !== []) {
            // The pointer goes into a paths step: finish its branch, then carry on after it.
            $branch = array_shift($resume);
            $paths = $steps[$start];

            if (! $paths instanceof PathsStepData) {
                throw new AutomationException(__('The run can not be resumed: its definition no longer matches.'));
            }

            $inner = $branch === 'else' ? $paths->else : $paths->branches[(int) $branch]->steps;

            if (! $this->steps($inner, $context, $run, [...$prefix, $start, $branch], $resume)) {
                return false;
            }

            $start++;
        }

        foreach ($steps as $index => $step) {
            if ($index < $start) {
                continue;
            }

            try {
                $continue = $this->step($step, $context, $run, $prefix, $index);
            } catch (Throwable $exception) {
                $this->log($run, $step, ['error' => $this->message($exception)]);

                throw $exception;
            }

            if (! $continue) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<int|string>  $prefix
     */
    protected function step(StepData $step, Context $context, AutomationRun $run, array $prefix, int $index): bool
    {
        return match (true) {
            $step instanceof ConditionStepData => $this->condition($step, $context, $run),
            $step instanceof ActionStepData => $this->action($step, $context, $run),
            $step instanceof PathsStepData => $this->paths($step, $context, $run, [...$prefix, $index]),
            $step instanceof StopStepData => $this->stop($step, $run),
            $step instanceof DelayStepData => $this->delay($step, $context, $run, [...$prefix, $index + 1]),
            default => throw new AutomationException(__('Unknown step type.')),
        };
    }

    protected function condition(ConditionStepData $step, Context $context, AutomationRun $run): bool
    {
        $result = $this->conditions->evaluate($step->match, $step->rules, $context);
        $this->log($run, $step, ['passed' => $result['passed'], 'condition' => $result]);

        if (! $result['passed']) {
            $run->state = [...$run->state, 'matched' => false];
        }

        return $result['passed'];
    }

    protected function action(ActionStepData $step, Context $context, AutomationRun $run): bool
    {
        $action = $this->registry->action($step->action) ?? throw new AutomationException(__('The action “:action” does not exist.', ['action' => $step->action]));

        if ($context->dryRun) {
            $this->log($run, $step, ['action' => $step->action, 'result' => $action->describe($context, $step->config)]);

            return true;
        }

        $context->stepId = $step->id;
        $result = $this->causation->during($run->getKey(), fn (): string => $action->execute($context, $step->config));
        $context->refresh();

        $this->log($run, $step, ['action' => $step->action, 'result' => $result]);

        return true;
    }

    /**
     * @param  list<int|string>  $path  Where this step sits in the definition.
     */
    protected function paths(PathsStepData $step, Context $context, AutomationRun $run, array $path): bool
    {
        foreach ($step->branches as $index => $branch) {
            $result = $this->conditions->evaluate($branch->condition->match, $branch->condition->rules, $context);

            if ($result['passed']) {
                $this->log($run, $step, ['branch' => $branch->name, 'branch_index' => $index, 'condition' => $result]);

                return $this->steps($branch->steps, $context, $run, [...$path, $index]);
            }
        }

        $this->log($run, $step, ['branch' => $step->else === [] ? null : 'else']);

        return $this->steps($step->else, $context, $run, [...$path, 'else']);
    }

    /**
     * Pause the run until the delay is over, unless that moment has passed already.
     *
     * The pointer in `state.resume` is the path to the next step to run: a list of step indexes
     * where each index that is followed by more entries is a paths step, then its branch (an index,
     * or `'else'`). `[3]` is the fourth top-level step; `[2, 0, 1]` is the second step of the first
     * branch of the paths step at index 2 (a pointer one past the end means that list is done).
     * `state.cancel_on` holds the trigger keys that cancel the wait.
     *
     * @param  list<int|string>  $pointer
     */
    protected function delay(DelayStepData $step, Context $context, AutomationRun $run, array $pointer): bool
    {
        $resumeAt = $this->resumeAt($step, $context);
        $waiting = $resumeAt->isFuture();
        $this->log($run, $step, ['resume_at' => $resumeAt->toIso8601String(), 'waiting' => $waiting] + ($context->dryRun ? ['note' => __('Test run: the delay is skipped and the steps after it are evaluated against the ticket as it is now.')] : []));

        if (! $waiting || $context->dryRun) {
            return true;
        }

        $run->state = [...$run->state, 'resume' => $pointer, 'cancel_on' => array_values($step->cancelOn)];
        $run->resume_at = $resumeAt;

        return false;
    }

    protected function resumeAt(DelayStepData $step, Context $context): CarbonImmutable
    {
        if ($step->for !== null) {
            $schedule = $step->for->businessScheduleId === null ? null
                : BusinessSchedule::query()->find($step->for->businessScheduleId) ?? throw new AutomationException(__('The business schedule of the delay does not exist.'));

            return BusinessSchedule::addMinutesFor($schedule, CarbonImmutable::now(), $step->for->unit->toMinutes($step->for->amount));
        }

        $field = $step->until === null ? null : $this->registry->field($step->until->field);
        $value = $field?->resolve($context);

        if ($value === null) {
            throw new AutomationException(__('The delay can not wait until a date that is not set.'));
        }

        return CarbonImmutable::parse($value)->addMinutes($step->until->offset);
    }

    protected function stop(StopStepData $step, AutomationRun $run): bool
    {
        $run->state = [...$run->state, 'stopped' => true];
        $this->log($run, $step, []);

        return false;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function log(AutomationRun $run, StepData $step, array $entry): void
    {
        $run->log = [...$run->log, ['step_id' => $step->id, 'type' => $step->type, ...$entry]];
    }

    /**
     * What may be stored and shown: rejections, authorization failures and our own exceptions
     * keep their message; anything else (a query error can carry SQL and data) gets a generic one.
     */
    protected function message(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ValidationException => implode(' ', Arr::flatten($exception->errors())),
            $this->isSafe($exception) => $exception->getMessage(),
            default => __('The step failed unexpectedly.'),
        };
    }

    protected function isSafe(Throwable $exception): bool
    {
        return $exception instanceof ValidationException
            || $exception instanceof AuthorizationException
            || $exception instanceof AutomationException;
    }
}
