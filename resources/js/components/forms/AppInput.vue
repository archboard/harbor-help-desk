<script setup lang="ts">
import { onMounted, useTemplateRef } from 'vue';

const {
  type = 'text',
  autofocus = false,
  hasError = false,
} = defineProps<{
  type?: string;
  autofocus?: boolean;
  hasError?: boolean;
}>();

const model = defineModel<string | number | null>();
const input = useTemplateRef<HTMLInputElement>('input');

onMounted(() => {
  if (autofocus) {
    input.value?.focus();
  }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
  <input
    ref="input"
    v-model="model"
    :type="type"
    class="block w-full rounded-lg bg-white text-sm shadow-xs focus:ring-2 focus:outline-hidden dark:bg-gray-900"
    :class="
      hasError
        ? 'border-red-300 pr-10 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500 dark:text-red-300 dark:placeholder-red-400/60'
        : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:focus:border-primary-500'
    "
  />
</template>
