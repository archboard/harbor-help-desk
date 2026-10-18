<script setup lang="ts">
const { darker = false } = defineProps<{ darker?: boolean }>();
</script>

<template>
  <td
    class="px-6 py-4 text-sm"
    :class="
      darker
        ? 'font-medium text-black dark:text-gray-100'
        : 'text-gray-700 dark:text-gray-300'
    "
  >
    <slot />
  </td>
</template>
