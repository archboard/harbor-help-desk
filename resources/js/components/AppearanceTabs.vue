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
  <div
    role="radiogroup"
    :aria-label="$t('Appearance')"
    class="flex gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-900"
  >
    <button
      v-for="{ value, Icon, label } in tabs"
      :key="value"
      type="button"
      role="radio"
      :aria-checked="appearance === value"
      :aria-label="label"
      :title="label"
      @click="updateAppearance(value)"
      :class="[
        'flex flex-1 items-center justify-center rounded-md py-1.5 transition-colors',
        appearance === value
          ? 'bg-white shadow-xs dark:bg-gray-700 dark:text-gray-100'
          : 'text-gray-500 hover:bg-gray-200/60 hover:text-black dark:text-gray-400 dark:hover:bg-gray-700/60',
      ]"
    >
      <component :is="Icon" class="size-4" aria-hidden="true" />
    </button>
  </div>
</template>
