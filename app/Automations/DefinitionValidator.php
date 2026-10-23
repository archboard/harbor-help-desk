<?php

namespace App\Automations;

use App\Automations\Fields\Field;
use App\Models\BusinessSchedule;
use App\Models\Desk;
use App\Models\Workspace;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Facades\Validator;

/**
 * Checks a raw automation definition against the registry: trigger and action configs, fields
 * available to the trigger, operator and value compatibility, delays, nested paths and size.
 *
 * Every error is keyed by the id of the step it belongs to (`steps.<id>.config.value`), also for
 * steps nested in paths, so the builder can mark the right card. Used as a validation rule on
 * the `definition` input, and by anything else that needs the same checks on an unsaved definition.
 */
class DefinitionValidator implements ValidationRule, ValidatorAwareRule
{
    public const int MAX_STEPS = 50;

    public const int MAX_DEPTH = 3;

    /** Field groups every trigger exposes. */
    protected const array ALWAYS_AVAILABLE = [FieldGroup::TICKET, FieldGroup::REQUESTER, FieldGroup::TIME, FieldGroup::SLA, FieldGroup::CUSTOM_FIELDS];

    protected ValidatorContract $validator;

    /** @var array<string, list<string>> */
    protected array $errors = [];

    /** @var array<string, true> */
    protected array $stepIds = [];

    protected int $stepCount = 0;

    protected ?Desk $scopeDesk = null;

    /** @var list<FieldGroup> */
    protected array $groups = self::ALWAYS_AVAILABLE;

    public function __construct(protected AutomationRegistry $registry, protected EditorScope $editorScope) {}

    public function setValidator(ValidatorContract $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach ($this->errors($value) as $key => $messages) {
            foreach ($messages as $message) {
                $this->validator->errors()->add("$attribute.$key", $message);
            }
        }
    }

    /**
     * @return array<string, list<string>> Messages by key, relative to the definition.
     */
    public function errors(mixed $definition): array
    {
        $this->errors = [];
        $this->stepIds = [];
        $this->stepCount = 0;
        $this->scopeDesk = null;
        $this->groups = self::ALWAYS_AVAILABLE;

        if (! is_array($definition)) {
            return ['' => [__('The definition must be an object.')]];
        }

        $this->trigger($definition['trigger'] ?? null);
        $this->steps($definition['steps'] ?? [], 'steps', 1);

        if ($this->stepCount > self::MAX_STEPS) {
            $this->add('steps', __('An automation can have at most :max steps.', ['max' => self::MAX_STEPS]));
        }

        return $this->errors;
    }

    protected function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    protected function add(string $key, string $message): void
    {
        $this->errors[$key][] = $message;
    }

    protected function trigger(mixed $trigger): void
    {
        if (! is_array($trigger)) {
            $this->add('trigger', __('An automation needs a trigger.'));

            return;
        }

        $instance = is_string($trigger['type'] ?? null) ? $this->registry->trigger($trigger['type']) : null;

        if ($instance === null) {
            $this->add('trigger.type', __('This trigger does not exist.'));

            return;
        }

        $this->groups = [...self::ALWAYS_AVAILABLE, ...$instance->fieldGroups()];
        $this->config($trigger['config'] ?? [], $instance->configRules(), 'trigger.config');
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    protected function config(mixed $config, array $rules, string $prefix): void
    {
        if (! is_array($config)) {
            $this->add($prefix, __('The config must be an object.'));

            return;
        }

        foreach (Validator::make($config, $rules)->errors()->toArray() as $key => $messages) {
            foreach ($messages as $message) {
                $this->add("$prefix.$key", $message);
            }
        }
    }

    protected function steps(mixed $steps, string $prefix, int $depth): void
    {
        if (! is_array($steps) || ! array_is_list($steps)) {
            $this->add($prefix, __('Steps must be a list.'));

            return;
        }

        $this->stepCount += count($steps);

        foreach ($steps as $index => $step) {
            $this->step($step, $depth);

            // A stop ends the run, so a step after it could never run.
            if (is_array($step) && ($step['type'] ?? null) === 'stop' && $index < count($steps) - 1 && is_string($step['id'] ?? null)) {
                $this->add("steps.{$step['id']}.type", __('A stop must be the last step.'));
            }
        }
    }

    protected function step(mixed $step, int $depth): void
    {
        $id = is_array($step) ? ($step['id'] ?? null) : null;

        if (! is_string($id) || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1) {
            $this->add('steps.invalid.id', __('Every step needs an id of letters, numbers, dashes and underscores.'));

            return;
        }

        if (isset($this->stepIds[$id])) {
            $this->add("steps.$id.id", __('Step ids must be unique.'));

            return;
        }

        $this->stepIds[$id] = true;
        $key = "steps.$id";

        match ($step['type'] ?? null) {
            'condition' => $this->group($step, $key, 1),
            'action' => $this->action($step, $key),
            'delay' => $this->delay($step, $key),
            'paths' => $this->paths($step, $key, $depth),
            'stop' => null,
            default => $this->add("$key.type", __('This step type does not exist.')),
        };
    }

    /**
     * A condition step or group: a match mode and its rules.
     *
     * @param  array<string, mixed>  $group
     */
    protected function group(array $group, string $key, int $level): void
    {
        if (MatchMode::tryFrom($this->text($group['match'] ?? null)) === null) {
            $this->add("$key.match", __('Match must be all or any.'));
        }

        $rules = $group['rules'] ?? null;

        if (! is_array($rules) || ! array_is_list($rules)) {
            $this->add("$key.rules", __('Rules must be a list.'));

            return;
        }

        foreach ($rules as $index => $node) {
            $nodeKey = "$key.rules.$index";

            match (is_array($node) ? ($node['type'] ?? null) : null) {
                'rule' => $this->rule($node, $nodeKey),
                'group' => $level >= self::MAX_DEPTH
                    ? $this->add($nodeKey, __('Groups can be nested at most :max levels deep.', ['max' => self::MAX_DEPTH]))
                    : $this->group($node, $nodeKey, $level + 1),
                default => $this->add("$nodeKey.type", __('A rule must be a rule or a group.')),
            };
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function rule(array $rule, string $key): void
    {
        $field = $this->field($rule['field'] ?? null, "$key.field");
        $operator = Operator::tryFrom($this->text($rule['operator'] ?? null));

        if ($operator === null) {
            $this->add("$key.operator", __('This operator does not exist.'));
        }

        if ($field === null || $operator === null) {
            return;
        }

        if (! $operator->supports($field->type())) {
            $this->add("$key.operator", __('This operator can not be used with this field.'));

            return;
        }

        if ($operator->readsChange() && ! in_array(FieldGroup::EVENT, $this->groups, true)) {
            $this->add("$key.operator", __('This trigger does not report changes.'));

            return;
        }

        $value = $rule['value'] ?? null;

        if (! $operator->takesValue()) {
            if ($value !== null) {
                $this->add("$key.value", __('This operator takes no value.'));
            }

            return;
        }

        if ($value === null || $value === '' || $value === []) {
            $this->add("$key.value", __('A value is required.'));

            return;
        }

        $this->value($field->type(), $field->options(), $operator, $value, "$key.value");
    }

    /**
     * The field when it exists in the current workspace and the trigger exposes it.
     */
    protected function field(mixed $key, string $errorKey): ?Field
    {
        $field = is_string($key) ? $this->registry->field($key) : null;

        if ($field === null) {
            $this->add($errorKey, __('This field does not exist.'));
        } elseif (! in_array($field->group(), $this->groups, true)) {
            $this->add($errorKey, __('This field is not available for the trigger.'));

            return null;
        }

        return $field;
    }

    /**
     * @param  array<string, string>  $options
     */
    protected function value(FieldType $type, array $options, Operator $operator, mixed $value, string $key): void
    {
        if ($operator === Operator::MATCHES) {
            if (! Operator::isValidPattern($value)) {
                $this->add($key, __('This is not a valid pattern.'));
            }

            return;
        }

        if ($type === FieldType::DURATION) {
            $this->duration($value, $key);

            return;
        }

        $values = in_array($operator, [Operator::IN, Operator::NOT_IN, Operator::INCLUDES_ANY, Operator::INCLUDES_ALL, Operator::EXCLUDES], true) ? $value : [$value];

        if (! is_array($values) || ! array_is_list($values) || array_any($values, fn (mixed $item): bool => ! is_scalar($item))) {
            $this->add($key, __('This value has the wrong shape for the operator.'));

            return;
        }

        $valid = match ($type) {
            FieldType::NUMBER => array_all($values, fn (mixed $item): bool => is_numeric($item)),
            FieldType::BOOLEAN => array_all($values, fn (mixed $item): bool => filter_var($item, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null),
            FieldType::DATETIME => array_all($values, fn (mixed $item): bool => is_string($item) && strtotime($item) !== false),
            FieldType::ENUM, FieldType::DESK => array_all($values, fn (mixed $item): bool => array_key_exists((string) $item, $options)),
            FieldType::MULTI_ENUM => array_all($values, fn (mixed $item): bool => is_string($item) && ($options === [] || array_key_exists($item, $options))),
            FieldType::USER, FieldType::USERS => $this->usersExist($values),
            default => array_all($values, fn (mixed $item): bool => is_string($item)),
        };

        if (! $valid) {
            $this->add($key, __('This value is not valid for the field.'));
        }
    }

    /**
     * @param  list<mixed>  $ids
     */
    protected function usersExist(array $ids): bool
    {
        $ids = array_unique(array_map(strval(...), $ids));

        return Workspace::current()?->users()->whereKey($ids)->count() === count($ids);
    }

    /**
     * A duration value: `{amount, unit, business_schedule_id?}`.
     */
    protected function duration(mixed $value, string $key): void
    {
        if (! is_array($value)) {
            $this->add($key, __('A duration needs an amount and a unit.'));

            return;
        }

        $this->interval($value, $key, 0);
    }

    /**
     * @param  array<string, mixed>  $interval
     */
    protected function interval(array $interval, string $key, int $minimum): void
    {
        if (filter_var($interval['amount'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]) === false) {
            $this->add("$key.amount", __('The amount must be a whole number.'));
        }

        if (DelayUnit::tryFrom($this->text($interval['unit'] ?? null)) === null) {
            $this->add("$key.unit", __('The unit must be minutes, hours or days.'));
        }

        $scheduleId = $interval['business_schedule_id'] ?? null;

        if ($scheduleId !== null && (! is_string($scheduleId) || ! BusinessSchedule::query()->whereKey($scheduleId)->exists())) {
            $this->add("$key.business_schedule_id", __('This business schedule does not exist.'));
        }
    }

    /**
     * @param  array<string, mixed>  $step
     */
    protected function action(array $step, string $key): void
    {
        $action = is_string($step['action'] ?? null) ? $this->registry->action($step['action']) : null;

        if ($action === null) {
            $this->add("$key.action", __('This action does not exist.'));

            return;
        }

        $this->config($step['config'] ?? [], array_merge_recursive(
            $action->configRules(),
            $action->contextRules($this->editorScope->editor(), $this->scopeDesk ??= $this->editorScope->desk()),
        ), "$key.config");
    }

    /**
     * @param  array<string, mixed>  $step
     */
    protected function delay(array $step, string $key): void
    {
        $for = $step['for'] ?? null;
        $until = $step['until'] ?? null;

        if (($for === null) === ($until === null)) {
            $this->add("$key.for", __('A delay needs either a duration or a date to wait until.'));
        } elseif ($for !== null) {
            is_array($for) ? $this->interval($for, "$key.for", 1) : $this->add("$key.for", __('A duration needs an amount and a unit.'));
        } elseif (! is_array($until)) {
            $this->add("$key.until", __('Until needs a field and an offset.'));
        } else {
            $field = $this->field($until['field'] ?? null, "$key.until.field");

            if ($field !== null && $field->type() !== FieldType::DATETIME) {
                $this->add("$key.until.field", __('A delay can only wait until a date field.'));
            }

            if (filter_var($until['offset'] ?? 0, FILTER_VALIDATE_INT) === false) {
                $this->add("$key.until.offset", __('The offset must be a whole number of minutes.'));
            }
        }

        $cancelOn = $step['cancel_on'] ?? [];

        if (! is_array($cancelOn)) {
            $this->add("$key.cancel_on", __('Cancel on must be a list of trigger types.'));

            return;
        }

        foreach ($cancelOn as $index => $type) {
            if (! is_string($type) || $this->registry->trigger($type) === null) {
                $this->add("$key.cancel_on.$index", __('This trigger does not exist.'));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $step
     */
    protected function paths(array $step, string $key, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            $this->add($key, __('Paths can be nested at most :max levels deep.', ['max' => self::MAX_DEPTH]));

            return;
        }

        $branches = $step['branches'] ?? null;

        if (! is_array($branches) || ! array_is_list($branches) || $branches === []) {
            $this->add("$key.branches", __('Paths need at least one branch.'));
        } else {
            foreach ($branches as $index => $branch) {
                $branchKey = "$key.branches.$index";

                if (! is_array($branch)) {
                    $this->add($branchKey, __('A branch must be an object.'));

                    continue;
                }

                if (! is_string($branch['name'] ?? null) || trim($branch['name']) === '') {
                    $this->add("$branchKey.name", __('A branch needs a name.'));
                }

                is_array($branch['condition'] ?? null)
                    ? $this->group($branch['condition'], "$branchKey.condition", 1)
                    : $this->add("$branchKey.condition", __('A branch needs a condition.'));

                $this->steps($branch['steps'] ?? [], "$branchKey.steps", $depth + 1);
            }
        }

        $this->steps($step['else'] ?? [], "$key.else", $depth + 1);
    }
}
