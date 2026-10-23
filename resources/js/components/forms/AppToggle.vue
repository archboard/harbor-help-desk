<script setup lang="ts">
import { Switch } from '@headlessui/vue';

/** An on/off switch. The slot is its accessible label. */
const model = defineModel<boolean>({ default: false });
</script>

<template>
  <Switch
    v-model="model"
    :class="[
      'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:outline-hidden',
      model ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-700',
    ]"
  >
    <span class="sr-only"><slot /></span>
    <span
      :class="[
        'pointer-events-none inline-block size-5 rounded-full bg-white shadow-sm transition-transform',
        model ? 'translate-x-5' : 'translate-x-0',
      ]"
    />
  </Switch>
</template>
