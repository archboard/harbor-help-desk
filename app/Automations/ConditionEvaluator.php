<?php

namespace App\Automations;

use App\Data\Automations\ConditionGroupData;
use App\Data\Automations\ConditionNodeData;
use App\Data\Automations\RuleData;
use App\Models\BusinessSchedule;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Evaluates a condition against a {@see Context} and records what each rule compared, for the
 * run log. A rule that can't be evaluated (unknown field, an operator the field can't use, a
 * value that doesn't parse) fails with an `error` instead of throwing.
 *
 * Every rule is evaluated, even after the result is settled, so the log shows all the values.
 * A group without rules passes.
 */
class ConditionEvaluator
{
    public function __construct(protected AutomationRegistry $registry) {}

    /**
     * @param  list<ConditionNodeData>  $rules
     * @return array{type: string, match: string, passed: bool, rules: list<array<string, mixed>>}
     */
    public function evaluate(MatchMode $match, array $rules, Context $context): array
    {
        $results = array_map(fn (ConditionNodeData $node): array => $node instanceof ConditionGroupData
            ? $this->evaluate($node->match, $node->rules, $context)
            : $this->evaluateRule($node, $context), $rules);

        $passes = array_column($results, 'passed');

        return [
            'type' => 'group',
            'match' => $match->value,
            'passed' => $rules === [] || ($match === MatchMode::ALL ? ! in_array(false, $passes, true) : in_array(true, $passes, true)),
            'rules' => $results,
        ];
    }

    /**
     * @return array{type: string, field: string, operator: string, expected: mixed, actual: mixed, passed: bool, error?: string}
     */
    protected function evaluateRule(RuleData $rule, Context $context): array
    {
        $log = ['type' => 'rule', 'field' => $rule->field, 'operator' => $rule->operator->value, 'expected' => $rule->value, 'actual' => null];
        $field = $this->registry->field($rule->field);

        if ($field === null) {
            return $log + ['passed' => false, 'error' => __('Unknown field “:field”.', ['field' => $rule->field])];
        }

        if (! $rule->operator->supports($field->type())) {
            return $log + ['passed' => false, 'error' => __('“:operator” can’t be used with “:field”.', ['operator' => $rule->operator->label(), 'field' => $field->label()])];
        }

        try {
            $expected = $rule->value;
            $schedule = null;

            if ($field->type() === FieldType::DURATION && is_array($expected)) {
                $schedule = $this->schedule($expected['business_schedule_id'] ?? null, $context);
                $expected = DelayUnit::from($expected['unit'] ?? 'minutes')->toMinutes((int) ($expected['amount'] ?? 0));
            }

            if ($rule->operator->readsChange()) {
                $change = $context->change();
                $actual = $change !== null && $change['field'] === $field->key() ? $change : null;
            } else {
                $actual = $field->resolve($context, $schedule);
            }

            return array_merge($log, [
                'actual' => $actual instanceof CarbonInterface ? $actual->toIso8601String() : $actual,
                'passed' => $rule->operator->apply($actual, $expected),
            ]);
        } catch (Throwable $exception) {
            return $log + ['passed' => false, 'error' => $exception->getMessage()];
        }
    }

    protected function schedule(?string $id, Context $context): ?BusinessSchedule
    {
        return $id === null ? null : $context->memo("schedule.$id", fn (): BusinessSchedule => BusinessSchedule::query()->findOrFail($id));
    }
}
