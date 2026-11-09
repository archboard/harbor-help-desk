<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { ChevronDown } from '@lucide/vue';
import dayjs from 'dayjs';
import { ref, watch } from 'vue';
import AppCheckbox from '@/components/forms/AppCheckbox.vue';

export type ReportQuery = {
  from: string;
  to: string;
  desk_ids: string[];
  basis: App.Enums.ReportBasis;
};

const { period, desks, bases } = defineProps<{
  period: App.Data.Reports.ReportPeriodData;
  desks: App.Data.Desks.DeskData[];
  bases: Record<App.Enums.ReportBasis, string>;
}>();

const emit = defineEmits<{ change: [query: ReportQuery] }>();

const presets = [7, 30, 90];
const from = ref(period.from);
const to = ref(period.to);
const deskIds = ref<string[]>([...period.desk_ids]);

watch(
  () => period,
  () => {
    from.value = period.from;
    to.value = period.to;
    deskIds.value = [...period.desk_ids];
  },
);

const today = () => dayjs().tz(period.timezone).format('YYYY-MM-DD');

function isPreset(days: number): boolean {
  return (
    to.value === today() &&
    from.value ===
      dayjs(today())
        .subtract(days - 1, 'day')
        .format('YYYY-MM-DD')
  );
}

function apply(changes: Partial<ReportQuery> = {}): void {
  emit('change', {
    from: from.value,
    to: to.value,
    desk_ids: deskIds.value,
    basis: period.basis,
    ...changes,
  });
}

function usePreset(days: number): void {
  to.value = today();
  from.value = dayjs(to.value)
    .subtract(days - 1, 'day')
    .format('YYYY-MM-DD');
  apply();
}

function applyDates(): void {
  if (from.value && to.value && from.value <= to.value) {
    apply();
  }
}

const segment =
  'px-3 py-1.5 text-sm font-medium first:rounded-l-lg last:rounded-r-lg focus:z-10 focus:ring-2 focus:ring-primary-500 focus:outline-hidden';
const segmentState = (active: boolean): string =>
  active
    ? 'bg-primary-600 text-white'
    : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800';
</script>

<template>
  <div class="flex flex-wrap items-center gap-3">
    <div
      class="inline-flex rounded-lg shadow-sm ring-1 ring-gray-300 dark:ring-gray-700"
      role="group"
      :aria-label="$t('Date range')"
    >
      <button
        v-for="days in presets"
        :key="days"
        type="button"
        :class="[segment, segmentState(isPreset(days))]"
        :aria-pressed="isPreset(days)"
        @click="usePreset(days)"
      >
        {{ $t(':count days', { count: String(days) }) }}
      </button>
    </div>

    <div class="flex items-center gap-2 text-sm">
      <input
        v-model="from"
        type="date"
        :aria-label="$t('From')"
        :max="to"
        class="rounded-md border-gray-300 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-900"
        @change="applyDates"
      />
      <span class="text-gray-500">{{ $t('to') }}</span>
      <input
        v-model="to"
        type="date"
        :aria-label="$t('To')"
        :min="from"
        class="rounded-md border-gray-300 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-900"
        @change="applyDates"
      />
    </div>

    <Popover class="relative">
      <PopoverButton
        class="inline-flex items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-gray-800"
      >
        {{
          deskIds.length === 0
            ? $t('All desks')
            : $tChoice('{1} :count desk|[2,*] :count desks', deskIds.length, {
                count: String(deskIds.length),
              })
        }}
        <ChevronDown class="size-4" aria-hidden="true" />
      </PopoverButton>
      <PopoverPanel
        class="absolute z-20 mt-2 max-h-72 w-64 overflow-y-auto rounded-xl bg-white p-3 shadow-lg ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10"
      >
        <fieldset class="space-y-2">
          <legend class="sr-only">{{ $t('Desks') }}</legend>
          <div v-for="desk in desks" :key="desk.id">
            <AppCheckbox v-model="deskIds" :value="desk.id" @change="apply()">
              {{ desk.name }}
              <span v-if="desk.archived_at" class="text-gray-500">{{
                $t('(archived)')
              }}</span>
            </AppCheckbox>
          </div>
        </fieldset>
      </PopoverPanel>
    </Popover>

    <div
      class="inline-flex rounded-lg shadow-sm ring-1 ring-gray-300 dark:ring-gray-700"
      role="group"
      :aria-label="$t('Count time in')"
    >
      <button
        v-for="(label, basis) in bases"
        :key="basis"
        type="button"
        :class="[segment, segmentState(period.basis === basis)]"
        :aria-pressed="period.basis === basis"
        @click="apply({ basis })"
      >
        {{ label }}
      </button>
    </div>
  </div>
</template>
