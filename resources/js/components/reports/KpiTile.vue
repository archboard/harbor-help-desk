<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import type { ValueKind } from '@/lib/reports';
import { formatValue } from '@/lib/reports';

const {
  label,
  kpi,
  kind,
  goodWhen = 'up',
} = defineProps<{
  label: string;
  kpi: App.Data.Reports.KpiData;
  kind: ValueKind;
  /** Which way the number should move; `neutral` shows the change uncolored. */
  goodWhen?: 'up' | 'down' | 'neutral';
}>();

const change = computed(() => {
  if (kpi.value === null || kpi.previous === null) {
    return null;
  }

  const difference = kpi.value - kpi.previous;
  const text =
    kind === 'percent'
      ? trans(':count pts', {
          count: String(Number(Math.abs(difference).toFixed(1))),
        })
      : formatValue(Math.abs(difference), kind);
  const isGood =
    goodWhen === 'neutral' || difference === 0
      ? null
      : difference > 0 === (goodWhen === 'up');

  return { difference, text, isGood };
});
</script>

<template>
  <div class="rounded-2xl bg-white p-5 shadow-sm dark:bg-gray-900">
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ label }}</p>
    <p
      class="mt-1 text-3xl font-semibold text-gray-900 tabular-nums dark:text-gray-100"
    >
      {{ formatValue(kpi.value, kind) }}
    </p>
    <p class="mt-1 text-xs text-gray-500 tabular-nums dark:text-gray-400">
      <template v-if="change">
        <span
          :class="{
            'text-green-700 dark:text-green-400': change.isGood === true,
            'text-red-700 dark:text-red-400': change.isGood === false,
          }"
        >
          <span aria-hidden="true">{{
            change.difference > 0 ? '▲' : change.difference < 0 ? '▼' : '='
          }}</span>
          <span class="sr-only">{{
            change.difference > 0
              ? $t('Up')
              : change.difference < 0
                ? $t('Down')
                : $t('No change')
          }}</span>
          {{ change.difference === 0 ? '' : change.text }}
        </span>
        {{ $t('vs :value before', { value: formatValue(kpi.previous, kind) }) }}
      </template>
      <template v-else>
        {{
          kpi.previous === null
            ? $t('Nothing to compare with')
            : $t('vs :value before', {
                value: formatValue(kpi.previous, kind),
              })
        }}
      </template>
    </p>
  </div>
</template>
