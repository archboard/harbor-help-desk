<script setup lang="ts">
import { computed } from 'vue';
import { fromLocalDateTime, toLocalDateTime } from '@/lib/dates';
import FormField from '@/components/forms/FormField.vue';
import {
  CheckboxField,
  CheckboxListField,
  InputField,
  NumberField,
  SelectField,
  TextareaField,
} from '@/components/forms/fields';

/**
 * An input for a value of a catalog field, picked from the field's type. The value is a
 * string, a number, a boolean, or a list of strings for the multiple-choice types.
 */
const {
  field,
  label,
  error,
  required = false,
} = defineProps<{
  field: App.Data.Automations.CatalogFieldData;
  label?: string;
  error?: string;
  required?: boolean;
}>();

const model = defineModel<unknown>();

// ponytail: absolute dates only. Relative ones ("2 days from now") come with the condition and action editors.
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

const empty = (value: unknown): unknown => (value === '' ? null : value);
</script>

<template>
  <SelectField
    v-if="['enum', 'desk', 'user'].includes(field.type)"
    :model-value="text"
    :label="label"
    :options="field.options"
    :required="required"
    :error="error"
    @update:model-value="model = empty($event)"
  />
  <CheckboxListField
    v-else-if="['multi_enum', 'users'].includes(field.type)"
    :model-value="(model as string[] | null | undefined) ?? []"
    :label="label"
    :options="field.options"
    :required="required"
    :error="error"
    @update:model-value="model = $event"
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
  <CheckboxField
    v-else-if="field.type === 'boolean'"
    :model-value="model === true"
    :label="label"
    :error="error"
    @update:model-value="model = $event"
  />
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
