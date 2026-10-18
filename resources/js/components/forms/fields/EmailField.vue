<script setup lang="ts">
import FormField from '@/components/forms/FormField.vue';
import type { FieldProps } from '@/types';

const {
  label,
  error,
  placeholder,
  disabled = false,
  required = false,
  helpText,
} = defineProps<FieldProps>();

const model = defineModel<string | null>();
</script>

<template>
  <FormField
    v-model="model"
    type="email"
    :error="error"
    :help="helpText"
    :required="required"
    :disabled="disabled"
    :placeholder="placeholder"
  >
    {{ label }}
  </FormField>
</template>
