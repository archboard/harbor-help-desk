<?php

namespace App\Automations;

use App\Data\Automations\ActionStepData;
use App\Data\Automations\ConditionStepData;
use App\Data\Automations\DelayStepData;
use App\Data\Automations\PathsStepData;
use App\Data\Automations\StepData;
use App\Data\Automations\StopStepData;
use App\Enums\AutomationRunStatus;
use App\Models\Automation;
use App\Models\AutomationRun;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Walks the steps of a run's definition snapshot and records one log entry per step.
 *
 * - A condition that fails ends the run as `completed` with `state.matched = false`.
 * - An action runs as the automation, and the events it causes carry the run's id ({@see RunCausation}).
 * - Paths run the first branch whose condition passes, else the `else` steps.
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
        $run->status = AutomationRunStatus::RUNNING;

        try {
            $this->steps($run->definition->steps, $context, $run);
            $run->status = AutomationRunStatus::COMPLETED;
        } catch (Throwable $exception) {
            $run->status = AutomationRunStatus::FAILED;
            $run->error = $this->message($exception);

            $context = ['automation_id' => $automation->getKey(), 'run_id' => $run->getKey(), 'error' => $run->error];
            $this->isSafe($exception)
                ? Log::warning('Automation step failed.', $context)
                : report($exception);
        }

        $run->save();

        if ($run->status === AutomationRunStatus::FAILED) {
            $this->notifyCreator->handle($automation, $run);
        }

        return $run;
    }

    /**
     * @param  array<int, StepData>  $steps
     * @return bool False when the run has ended: a condition failed or a stop was reached.
     */
    protected function steps(array $steps, Context $context, AutomationRun $run): bool
    {
        foreach ($steps as $step) {
            try {
                $continue = $this->step($step, $context, $run);
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

    protected function step(StepData $step, Context $context, AutomationRun $run): bool
    {
        return match (true) {
            $step instanceof ConditionStepData => $this->condition($step, $context, $run),
            $step instanceof ActionStepData => $this->action($step, $context, $run),
            $step instanceof PathsStepData => $this->paths($step, $context, $run),
            $step instanceof StopStepData => $this->stop($step, $run),
            // Delays and resuming come with the scheduler step (A5).
            $step instanceof DelayStepData => throw new AutomationException(__('Delays are not supported yet.')),
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

        $result = $this->causation->during($run->getKey(), fn (): string => $action->execute($context, $step->config));
        $context->refresh();

        $this->log($run, $step, ['action' => $step->action, 'result' => $result]);

        return true;
    }

    protected function paths(PathsStepData $step, Context $context, AutomationRun $run): bool
    {
        foreach ($step->branches as $index => $branch) {
            $result = $this->conditions->evaluate($branch->condition->match, $branch->condition->rules, $context);

            if ($result['passed']) {
                $this->log($run, $step, ['branch' => $branch->name, 'branch_index' => $index, 'condition' => $result]);

                return $this->steps($branch->steps, $context, $run);
            }
        }

        $this->log($run, $step, ['branch' => $step->else === [] ? null : 'else']);

        return $this->steps($step->else, $context, $run);
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
