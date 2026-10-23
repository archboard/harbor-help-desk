<script setup lang="ts">
import { computed } from 'vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import FieldError from '@/components/forms/FieldError.vue';
import { defaultValue, operatorsFor, valueShape } from './conditions';
import type { Rule } from './conditions';
import { errorsUnder } from './errors';
import FieldPicker from './FieldPicker.vue';
import FieldValueInput from './FieldValueInput.vue';
import { fieldFor } from './summaries';

/**
 * One rule: a field, an operator that fits the field's type, and a value input that fits both.
 */
const {
  catalog,
  groups,
  schedules = [],
  errors = {},
} = defineProps<{
  catalog: App.Data.Automations.AutomationCatalogData;
  /** The field groups the trigger makes available. */
  groups: App.Automations.FieldGroup[];
  schedules?: App.Data.BusinessSchedules.BusinessScheduleData[];
  /** Server errors keyed `field`, `operator`, `value` and `value.amount`. */
  errors?: Record<string, string>;
}>();

const rule = defineModel<Rule>({ required: true });

const field = computed(() => fieldFor(catalog, rule.value.field));
const operators = computed(() => operatorsFor(catalog, field.value, groups));
const shape = computed(() =>
  valueShape(catalog, field.value, rule.value.operator),
);

/** A relative date's errors sit one level deeper (`value.relative.amount`). */
const valueErrors = computed(() =>
  errorsUnder(
    errors,
    rule.value.value &&
      typeof rule.value.value === 'object' &&
      'relative' in rule.value.value
      ? 'value.relative.'
      : 'value.',
  ),
);

function setField(key: string): void {
  const next = fieldFor(catalog, key);
  const available = operatorsFor(catalog, next, groups);
  const operator = available.some((item) => item.value === rule.value.operator)
    ? rule.value.operator
    : (available[0]?.value ?? rule.value.operator);

  rule.value = {
    ...rule.value,
    field: key,
    operator,
    value: defaultValue(next, valueShape(catalog, next, operator)),
  };
}

function setOperator(operator: Rule['operator']): void {
  const before = shape.value;
  const after = valueShape(catalog, field.value, operator);

  rule.value = {
    ...rule.value,
    operator,
    value:
      before === after ? rule.value.value : defaultValue(field.value, after),
  };
}
</script>

<template>
  <div class="grid min-w-0 flex-1 items-start gap-3 md:grid-cols-3">
    <div class="space-y-1">
      <FieldPicker
        :model-value="rule.field"
        :catalog="catalog"
        :groups="groups"
        :has-error="!!errors.field"
        @update:model-value="setField"
      />
      <FieldError v-if="errors.field">{{ errors.field }}</FieldError>
    </div>

    <div class="space-y-1">
      <AppSelect
        hide-null
        aria-label="Operator"
        :disabled="!field"
        :has-error="!!errors.operator"
        :options="
          operators.map((item) => ({ value: item.value, label: item.label }))
        "
        :model-value="rule.operator"
        @update:model-value="setOperator($event as Rule['operator'])"
      />
      <FieldError v-if="errors.operator">{{ errors.operator }}</FieldError>
    </div>

    <FieldValueInput
      v-if="field && shape !== 'none'"
      :class="{ 'md:col-span-3': field.type === 'datetime' }"
      v-model="rule.value"
      :field="field"
      :multiple="shape === 'list'"
      :pattern="rule.operator === 'matches'"
      relative
      :schedules="schedules"
      :error="errors.value"
      :errors="valueErrors"
    />
  </div>
</template>
