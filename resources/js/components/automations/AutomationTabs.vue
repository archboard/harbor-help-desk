<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { edit } from '@/routes/automations';
import { index } from '@/routes/automations/runs';

const { automationId, current } = defineProps<{
  automationId: string;
  current: 'builder' | 'history';
}>();

const tabs = computed(
  () =>
    [
      { key: 'builder', label: trans('Builder'), href: edit(automationId).url },
      {
        key: 'history',
        label: trans('History'),
        href: index(automationId).url,
      },
    ] as const,
);
</script>

<template>
  <nav
    class="flex gap-6 border-b border-gray-200 dark:border-gray-800"
    :aria-label="$t('Automation')"
  >
    <Link
      v-for="tab in tabs"
      :key="tab.key"
      :href="tab.href"
      class="-mb-px border-b-2 px-1 py-3 text-sm font-medium"
      :class="
        tab.key === current
          ? 'border-primary-500 text-primary-600 dark:text-primary-400'
          : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
      "
      :aria-current="tab.key === current ? 'page' : undefined"
    >
      {{ tab.label }}
    </Link>
  </nav>
</template>
