<script setup lang="ts">
import { onMounted, useTemplateRef } from 'vue';

const { autofocus = false, hasError = false } = defineProps<{
  autofocus?: boolean;
  hasError?: boolean;
}>();

const model = defineModel<string | null>();
const textarea = useTemplateRef<HTMLTextAreaElement>('textarea');

onMounted(() => {
  if (autofocus) {
    textarea.value?.focus();
  }
});
</script>

<template>
  <!-- ponytail: field-sizing grows with content natively; browsers without it keep a fixed 3-row box -->
  <textarea
    ref="textarea"
    v-model="model"
    rows="3"
    class="block field-sizing-content min-h-16 w-full rounded-lg bg-white text-sm shadow-xs focus:ring-2 focus:outline-hidden dark:bg-gray-900"
    :class="
      hasError
        ? 'border-red-300 pr-10 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500 dark:text-red-300 dark:placeholder-red-400/60'
        : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:focus:border-primary-500'
    "
  />
</template>
