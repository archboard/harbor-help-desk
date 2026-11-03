<script setup lang="ts">
const { title } = defineProps<{ title: string }>();
</script>

<template>
  <section
    class="rounded-2xl bg-white p-5 shadow-sm dark:bg-gray-900"
    :aria-label="title"
  >
    <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-gray-100">
      {{ title }}
    </h3>
    <slot />
  </section>
</template>
