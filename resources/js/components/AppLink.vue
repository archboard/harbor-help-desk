<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

const { is = Link, color = 'primary' } = defineProps<{
  is?: string | Component;
  color?: 'primary' | 'red';
}>();

const colors = {
  primary: 'text-primary-600 hover:underline dark:text-primary-400',
  red: 'text-red-600 hover:underline dark:text-red-400',
};
</script>

<template>
  <component
    :is="is"
    :class="[
      'cursor-pointer font-medium transition duration-100 ease-in-out focus:outline-hidden',
      colors[color],
    ]"
  >
    <slot />
  </component>
</template>
