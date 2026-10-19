<script setup lang="ts">
import { computed } from 'vue';

const { name } = defineProps<{ name: string | null | undefined }>();

/** Up to two initials, e.g. "Jane Doe" → "JD". */
const initials = computed(() =>
  (name ?? '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join(''),
);
</script>

<template>
  <span
    class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[0.625rem] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-200"
    :title="name ?? undefined"
    aria-hidden="true"
  >
    {{ initials }}
  </span>
</template>
