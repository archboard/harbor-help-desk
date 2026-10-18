<script setup lang="ts">
type PillColor =
  | 'gray'
  | 'yellow'
  | 'green'
  | 'red'
  | 'primary'
  | 'blue'
  | 'fuchsia';

const { color = 'gray', size = 'base' } = defineProps<{
  color?: PillColor;
  size?: 'base' | 'lg';
}>();

const colors: Record<PillColor, string> = {
  gray: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-50',
  yellow:
    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100',
  green: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100',
  red: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100',
  primary:
    'bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-100',
  blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100',
  fuchsia:
    'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-900 dark:text-fuchsia-100',
};

const sizes = {
  base: 'px-2 py-0.5 text-xs',
  lg: 'px-3 py-0.5 text-sm',
};
</script>

<template>
  <span
    :class="[
      'inline-flex items-center rounded-full font-medium whitespace-nowrap',
      sizes[size],
      colors[color],
    ]"
  >
    <slot />
  </span>
</template>
