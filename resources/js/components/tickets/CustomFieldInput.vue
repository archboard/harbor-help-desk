<script setup lang="ts">
import {
  CheckboxField,
  CheckboxListField,
  DatePickerField,
  InputField,
  NumberField,
  SelectField,
  TextareaField,
} from '@/components/forms/fields';

/**
 * The input for one custom field, picked by its type. Used on the ticket sidebar and the
 * new ticket form.
 */
defineProps<{
  field: App.Data.CustomFields.CustomFieldData;
  error?: string;
  disabled?: boolean;
}>();

// ponytail: one loose model for every type; each branch narrows it for its input.
const model = defineModel<any>();
</script>

<template>
  <TextareaField
    v-if="field.type === 'textarea'"
    v-model="model"
    :label="field.name"
    :error="error"
    :required="field.is_required"
    :disabled="disabled"
  />
  <NumberField
    v-else-if="field.type === 'number' || field.type === 'decimal'"
    v-model="model"
    :label="field.name"
    :error="error"
    :required="field.is_required"
    :disabled="disabled"
  />
  <DatePickerField
    v-else-if="field.type === 'date'"
    v-model="model"
    :label="field.name"
    :error="error"
    :required="field.is_required"
    :disabled="disabled"
  />
  <CheckboxField
    v-else-if="field.type === 'checkbox'"
    v-model="model"
    :label="field.name"
    :error="error"
    :disabled="disabled"
  />
  <SelectField
    v-else-if="field.type === 'select'"
    v-model="model"
    :label="field.name"
    :options="field.options ?? []"
    :error="error"
    :required="field.is_required"
    :disabled="disabled"
  />
  <CheckboxListField
    v-else-if="field.type === 'multi_select'"
    v-model="model"
    :label="field.name"
    :options="field.options ?? []"
    :error="error"
    :required="field.is_required"
    :disabled="disabled"
  />
  <InputField
    v-else
    v-model="model"
    :label="field.name"
    :error="error"
    :required="field.is_required"
    :disabled="disabled"
  />
</template>
