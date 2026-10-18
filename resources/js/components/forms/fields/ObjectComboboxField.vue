<script setup lang="ts">
import { ref } from 'vue';
import FormField from '@/components/forms/FormField.vue';
import GenericObjectCombobox from '@/components/forms/GenericObjectCombobox.vue';
import type { FieldProps } from '@/types';

type ObjectOption = { id: string | number; [key: string]: unknown };

const {
  label,
  error,
  disabled = false,
  required = false,
  helpText,
  options = [],
} = defineProps<FieldProps & { options?: ObjectOption[] }>();

const model = defineModel<string | number | null>();
const selected = ref<ObjectOption | null>(
  options.find((option) => option.id === model.value) ?? null,
);
</script>

<template>
  <FormField :error="error" :help="helpText" :required="required">
    {{ label }}
    <template #component="{ id }">
      <GenericObjectCombobox
        :id="id"
        v-model="selected"
        v-model:object-id="model"
        :options="options"
        :has-error="!!error"
        :disabled="disabled"
      />
    </template>
  </FormField>
</template>
