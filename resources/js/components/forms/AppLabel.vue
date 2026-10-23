<script setup lang="ts">
const { hasError = false } = defineProps<{ hasError?: boolean }>();
</script>

<template>
  <label
    :class="[
      'block text-sm font-medium empty:hidden',
      hasError
        ? 'text-red-700 dark:text-red-400'
        : 'text-gray-900 dark:text-gray-100',
    ]"
  >
    <slot />
  </label>
</template>
