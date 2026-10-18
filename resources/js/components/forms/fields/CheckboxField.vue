<script setup lang="ts">
import AppCheckbox from '@/components/forms/AppCheckbox.vue';
import FormField from '@/components/forms/FormField.vue';
import type { FieldProps } from '@/types';

const {
  label,
  error,
  disabled = false,
  required = false,
  helpText,
} = defineProps<FieldProps>();

const model = defineModel<boolean>({ default: false });
</script>

<template>
  <FormField :error="error" :help="helpText">
    <template #component>
      <AppCheckbox v-model="model" :required="required" :disabled="disabled">
        {{ label }}
      </AppCheckbox>
    </template>
  </FormField>
</template>
