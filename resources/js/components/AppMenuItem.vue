<script setup lang="ts">
import { MenuItem } from '@headlessui/vue';
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

defineOptions({ inheritAttrs: false });

const { is = Link } = defineProps<{ is?: string | Component }>();
</script>

<template>
  <MenuItem v-slot="{ active }">
    <component
      :is="is"
      :class="[
        active ? 'bg-gray-500 text-white' : 'text-black dark:text-gray-100',
        'group flex w-full cursor-pointer items-center rounded-sm px-4 py-2 text-sm focus:ring-1 focus:ring-gray-300 focus:outline-hidden dark:focus:ring-gray-500',
      ]"
      v-bind="$attrs"
    >
      <slot :active="active" />
    </component>
  </MenuItem>
</template>
