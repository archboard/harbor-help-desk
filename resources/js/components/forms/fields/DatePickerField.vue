<script setup lang="ts">
import AppDatepicker from '@/components/forms/AppDatepicker.vue';
import FormField from '@/components/forms/FormField.vue';
import type { FieldProps } from '@/types';

const {
  label,
  error,
  disabled = false,
  required = false,
  helpText,
  mode = 'date',
} = defineProps<FieldProps & { mode?: 'date' | 'datetime' }>();

const model = defineModel<string | null>();
</script>

<template>
  <FormField :error="error" :help="helpText" :required="required">
    {{ label }}
    <template #component="{ id }">
      <AppDatepicker
        :id="id"
        v-model="model"
        :mode="mode"
        :has-error="!!error"
        :disabled="disabled"
      />
    </template>
  </FormField>
</template>
