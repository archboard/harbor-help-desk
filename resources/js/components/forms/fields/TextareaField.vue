<script setup lang="ts">
import FormField from '@/components/forms/FormField.vue';
import type { FieldProps } from '@/types';

const {
  label,
  error,
  disabled = false,
  required = false,
  helpText,
} = defineProps<FieldProps>();

const model = defineModel<string | null>();
</script>

<template>
  <FormField
    v-model="model"
    component="AppTextarea"
    :error="error"
    :help="helpText"
    :required="required"
    :disabled="disabled"
  >
    {{ label }}
  </FormField>
</template>
