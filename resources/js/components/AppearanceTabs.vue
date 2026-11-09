<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = computed(
  () =>
    [
      { value: 'light', Icon: Sun, label: trans('Light') },
      { value: 'dark', Icon: Moon, label: trans('Dark') },
      { value: 'system', Icon: Monitor, label: trans('System') },
    ] as const,
);
</script>

<template>
  <div class="inline-flex gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
    <button
      v-for="{ value, Icon, label } in tabs"
      :key="value"
      @click="updateAppearance(value)"
      :class="[
        'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
        appearance === value
          ? 'bg-white shadow-xs dark:bg-gray-700 dark:text-gray-100'
          : 'text-gray-500 hover:bg-gray-200/60 hover:text-black dark:text-gray-400 dark:hover:bg-gray-700/60',
      ]"
    >
      <component :is="Icon" class="-ml-1 h-4 w-4" />
      <span class="ml-1.5 text-sm">{{ label }}</span>
    </button>
  </div>
</template>
