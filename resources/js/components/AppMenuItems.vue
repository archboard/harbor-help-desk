<script setup lang="ts">
import { MenuItems } from '@headlessui/vue';
</script>

<template>
  <MenuItems
    class="w-56 origin-top-right divide-y divide-gray-100 rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-hidden dark:divide-gray-700 dark:bg-gray-800 dark:ring-white/10"
  >
    <slot />
  </MenuItems>
</template>
