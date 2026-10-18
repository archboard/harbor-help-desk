<script setup lang="ts">
import { computed, useId } from 'vue';
import AppRadio from '@/components/forms/AppRadio.vue';
import FormField from '@/components/forms/FormField.vue';
import { normalizeOptions } from '@/lib/options';
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
const normalizedOptions = computed(() => normalizeOptions(options));
const name = `radio_${useId()}`;
</script>

<template>
  <FormField :error="error" :help="helpText" :required="required">
    {{ label }}
    <template #component>
      <div class="space-y-1">
        <AppRadio
          v-for="option in normalizedOptions"
          :key="String(option.value)"
          v-model="model"
          :value="option.value"
          :name="name"
          :disabled="disabled"
        >
          {{ option.label }}
        </AppRadio>
      </div>
    </template>
  </FormField>
</template>
