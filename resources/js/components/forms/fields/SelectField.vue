<script setup lang="ts">
import FormField from '@/components/forms/FormField.vue';
import type { FieldProps, OptionsInput } from '@/types';

const {
  label,
  error,
  disabled = false,
  required = false,
  helpText,
  options = [],
} = defineProps<FieldProps & { options?: OptionsInput }>();

const model = defineModel<string | number | boolean | null>();
</script>

<template>
  <FormField
    v-model="model"
    component="AppSelect"
    :options="options"
    :error="error"
    :help="helpText"
    :required="required"
    :disabled="disabled"
  >
    {{ label }}
  </FormField>
</template>
