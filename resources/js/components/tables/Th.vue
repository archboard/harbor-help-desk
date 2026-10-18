<script setup lang="ts">
const { right = false } = defineProps<{ right?: boolean }>();
</script>

<template>
  <th
    scope="col"
    :class="[
      'px-6 py-3 text-xs font-medium tracking-wider text-black dark:text-gray-100',
      right ? 'text-end' : 'text-start',
    ]"
  >
    <slot />
  </th>
</template>
