<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
  icon: Component;
  title?: string;
}>();
</script>

<template>
  <div
    class="flex flex-col items-center rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900"
  >
    <div
      class="flex size-12 items-center justify-center rounded-full bg-gray-100 ring-8 ring-gray-50 dark:bg-gray-800 dark:ring-gray-800/40"
    >
      <component
        :is="icon"
        class="size-6 text-gray-500 dark:text-gray-400"
        aria-hidden="true"
      />
    </div>
    <h3 class="mt-5 text-base font-semibold text-gray-900 dark:text-white">
      <slot name="title">{{ title }}</slot>
    </h3>
    <p
      v-if="$slots.default"
      class="mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400"
    >
      <slot />
    </p>
    <div v-if="$slots.actions" class="mt-6 flex flex-wrap justify-center gap-3">
      <slot name="actions" />
    </div>
  </div>
</template>
