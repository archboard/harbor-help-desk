<script setup lang="ts">
import { computed } from 'vue';
import { fromLocalDateTime, toLocalDateTime } from '@/lib/dates';
import AppLabel from '@/components/forms/AppLabel.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import AppToggle from '@/components/forms/AppToggle.vue';
import FormField from '@/components/forms/FormField.vue';
import TagsInput from '@/components/forms/TagsInput.vue';
import AssigneePicker from '@/components/tickets/AssigneePicker.vue';
import DurationInput from './DurationInput.vue';
import type { Interval } from './DurationInput.vue';
import {
  CheckboxListField,
  InputField,
  NumberField,
  SelectField,
  TextareaField,
} from '@/components/forms/fields';

/**
 * An input for a value of a catalog field, picked from the field's type. The value is a
 * string, a number, a boolean, a list of strings, a duration `{amount, unit, business_schedule_id?}`
 * or, for a date with `relative`, `{relative: {amount, unit, business_schedule_id?}}`.
 *
 * `multiple` says whether the value is a list. Left out, the multiple-choice types are lists
 * and every other type is a single value.
 */
const {
  field,
  label,
  error,
  required = false,
  multiple,
  pattern = false,
  relative = false,
  schedules = [],
  errors = {},
} = defineProps<{
  field: App.Data.Automations.CatalogFieldData;
  label?: string;
  error?: string;
  required?: boolean;
  multiple?: boolean;
  /** The value is a regular expression (the `matches` operator). */
  pattern?: boolean;
  /** Offer "3 days ago" next to an absolute date. */
  relative?: boolean;
  schedules?: App.Data.BusinessSchedules.BusinessScheduleData[];
  /** Server errors of a duration or relative date, keyed `amount`, `unit` and `business_schedule_id`. */
  errors?: Record<string, string>;
}>();

const model = defineModel<unknown>();

// A custom date field is stored as Y-m-d, every other datetime as a full date and time.
const dateType = computed(() =>
  field.key.startsWith('custom_field.') ? 'date' : 'datetime-local',
);

const text = computed(() =>
  model.value === undefined || model.value === null
    ? null
    : String(model.value as string | number | boolean),
);

/** A date and time is shown in the user's timezone and stored as ISO, so the server doesn't read it in its own. */
const dateText = computed(() =>
  dateType.value === 'datetime-local'
    ? (toLocalDateTime(text.value)?.replace(' ', 'T') ?? null)
    : text.value,
);

function setDate(value: unknown): void {
  model.value =
    dateType.value === 'datetime-local'
      ? fromLocalDateTime(typeof value === 'string' && value ? value : null)
      : empty(value);
}

const isList = computed(
  () => multiple ?? ['multi_enum', 'users'].includes(field.type),
);

/** Users pick from the pills-and-search list, tags and free lists type their values, the rest tick choices. */
const listKind = computed(() =>
  ['user', 'users'].includes(field.type)
    ? 'users'
    : field.key === 'ticket.tags' || !field.options.length
      ? 'tags'
      : 'choices',
);

const members = computed(() =>
  field.options.map((option) => ({ id: option.value, name: option.label })),
);

const relativeValue = computed(
  () =>
    (model.value as { relative?: Interval } | null | undefined)?.relative ??
    null,
);

function setDateMode(mode: unknown): void {
  model.value =
    mode === 'relative' ? { relative: { amount: -1, unit: 'days' } } : null;
}

const empty = (value: unknown): unknown => (value === '' ? null : value);
</script>

<template>
  <div v-if="field.type === 'duration'" class="space-y-1">
    <AppLabel v-if="label" :has-error="!!error">{{ label }}</AppLabel>
    <DurationInput
      :model-value="(model as Interval | null | undefined) ?? undefined"
      :schedules="schedules"
      :errors="errors"
      @update:model-value="model = $event"
    />
    <p v-if="error" class="text-sm font-medium text-red-600">{{ error }}</p>
  </div>
  <template v-else-if="isList">
    <AssigneePicker
      v-if="listKind === 'users'"
      :model-value="(model as string[] | null | undefined) ?? []"
      :members="members"
      @update:model-value="model = $event"
    />
    <TagsInput
      v-else-if="listKind === 'tags'"
      :model-value="(model as string[] | null | undefined) ?? []"
      :label="label"
      :required="required"
      :error="error"
      :options="field.options"
      @update:model-value="model = $event"
    />
    <CheckboxListField
      v-else
      :model-value="(model as string[] | null | undefined) ?? []"
      :label="label"
      :options="field.options"
      :required="required"
      :error="error"
      @update:model-value="model = $event"
    />
  </template>
  <div v-else-if="field.type === 'datetime' && relative" class="space-y-2">
    <AppLabel v-if="label" :has-error="!!error">{{ label }}</AppLabel>
    <div class="max-w-48">
      <AppSelect
        hide-null
        aria-label="Kind of date"
        :options="[
          { value: 'absolute', label: 'A date and time' },
          { value: 'relative', label: 'Relative to now' },
        ]"
        :model-value="relativeValue ? 'relative' : 'absolute'"
        @update:model-value="setDateMode"
      />
    </div>
    <DurationInput
      v-if="relativeValue"
      relative
      :model-value="relativeValue"
      :schedules="schedules"
      :errors="errors"
      @update:model-value="model = { relative: $event }"
    />
    <FormField
      v-else
      :type="dateType"
      :model-value="dateText"
      :required="required"
      :error="error"
      @update:model-value="setDate"
    />
  </div>
  <SelectField
    v-else-if="
      ['enum', 'desk', 'user', 'multi_enum', 'users'].includes(field.type)
    "
    :model-value="text"
    :label="label"
    :options="field.options"
    :required="required"
    :error="error"
    @update:model-value="model = empty($event)"
  />
  <NumberField
    v-else-if="field.type === 'number'"
    :model-value="model as number | null | undefined"
    :label="label"
    :required="required"
    :error="error"
    @update:model-value="
      model =
        $event === null || $event === ('' as never) ? null : Number($event)
    "
  />
  <FormField v-else-if="field.type === 'boolean'" :error="error">
    <template #component>
      <AppToggle
        :model-value="model === true"
        @update:model-value="model = $event"
      >
        {{ label }}
      </AppToggle>
    </template>
  </FormField>
  <FormField
    v-else-if="field.type === 'datetime'"
    :type="dateType"
    :model-value="dateText"
    :required="required"
    :error="error"
    @update:model-value="setDate"
  >
    {{ label }}
  </FormField>
  <InputField
    v-else-if="pattern"
    :model-value="text"
    :label="label"
    :required="required"
    :error="error"
    help-text="A regular expression, matched without regard to case. For example ^refund|chargeback"
    @update:model-value="model = empty($event)"
  />
  <TextareaField
    v-else-if="field.type === 'text'"
    :model-value="text"
    :label="label"
    :required="required"
    :error="error"
    @update:model-value="model = empty($event)"
  />
  <InputField
    v-else
    :model-value="text"
    :label="label"
    :required="required"
    :error="error"
    @update:model-value="model = empty($event)"
  />
</template>
