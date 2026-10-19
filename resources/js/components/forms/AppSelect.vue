<script setup lang="ts">
import { computed } from 'vue';
import { normalizeOptions } from '@/lib/options';
import type { OptionsInput } from '@/types';

const {
  hasError = false,
  options = [],
  nullOption = 'Select an option',
  hideNull = false,
  clearable = false,
} = defineProps<{
  hasError?: boolean;
  options?: OptionsInput;
  nullOption?: string;
  hideNull?: boolean;
  /** Lets the null option be chosen again, e.g. "Any status" in a filter. */
  clearable?: boolean;
}>();

const model = defineModel<string | number | boolean | null>();
const normalizedOptions = computed(() => normalizeOptions(options));
</script>

<template>
  <select
    v-model="model"
    class="block w-full rounded-md py-2 pr-8 pl-3 text-sm focus:ring-2 focus:outline-hidden dark:bg-gray-900"
    :class="
      hasError
        ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500'
        : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:focus:border-primary-500'
    "
  >
    <option v-if="!hideNull" :value="null" :disabled="!clearable">
      {{ nullOption }}
    </option>
    <slot>
      <option
        v-for="option in normalizedOptions"
        :key="String(option.value)"
        :value="option.value"
      >
        {{ option.label }}
      </option>
    </slot>
  </select>
</template>
