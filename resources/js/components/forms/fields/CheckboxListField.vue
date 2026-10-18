<script setup lang="ts">
import { computed } from 'vue';
import AppCheckbox from '@/components/forms/AppCheckbox.vue';
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

const model = defineModel<Array<string | number | boolean>>({
  default: () => [],
});
const normalizedOptions = computed(() => normalizeOptions(options));
</script>

<template>
  <FormField :error="error" :help="helpText" :required="required">
    {{ label }}
    <template #component>
      <div class="space-y-1">
        <div v-for="option in normalizedOptions" :key="String(option.value)">
          <AppCheckbox
            v-model="model"
            :value="option.value"
            :disabled="disabled"
          >
            {{ option.label }}
          </AppCheckbox>
        </div>
      </div>
    </template>
  </FormField>
</template>
