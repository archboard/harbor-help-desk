<script setup lang="ts">
import { SearchX, Activity } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import {
  Head,
  InfiniteScroll,
  Link,
  router,
  setLayoutProps,
} from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import { ref, watchEffect } from 'vue';
import AutomationTabs from '@/components/automations/AutomationTabs.vue';
import { runDuration, statusColors } from '@/components/automations/runStatus';
import AppInput from '@/components/forms/AppInput.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import Pill from '@/components/Pill.vue';
import Spinner from '@/components/Spinner.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { useLiveReload } from '@/composables/useLiveReload';
import { formatDateTime } from '@/lib/dates';
import { index as allRuns, show as showRun } from '@/routes/automation-runs';
import { index as automationRuns } from '@/routes/automations/runs';
import { show as showTicket } from '@/routes/tickets';
import type { Paginated } from '@/types';

defineOptions({ layout: { title: '' } });

const { automation, filters } = defineProps<{
  /** Null for the workspace-wide history. */
  automation: App.Data.Automations.AutomationData | null;
  runs: Paginated<App.Data.Automations.AutomationRunData>;
  filters: { status?: App.Enums.AutomationRunStatus; ticket_number?: string };
  statuses: Record<App.Enums.AutomationRunStatus, string>;
}>();

watchEffect(() =>
  setLayoutProps({
    title: automation ? trans('Automation history') : trans('Automation runs'),
  }),
);

const status = ref<string | null>(filters.status ?? null);
const ticketNumber = ref(filters.ticket_number ?? '');

watchDebounced(
  [status, ticketNumber],
  () => {
    const query = {
      ...(status.value ? { status: status.value } : {}),
      ...(ticketNumber.value.trim()
        ? { ticket_number: ticketNumber.value.trim() }
        : {}),
    };

    router.get(
      automation ? automationRuns(automation.id).url : allRuns().url,
      query,
      {
        only: ['runs', 'filters'],
        reset: ['runs'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
      },
    );
  },
  { debounce: 300 },
);

if (automation) {
  useLiveReload({
    channels: [`automations.${automation.id}`],
    event: '.AutomationRunChanged',
    only: ['runs'],
    reset: ['runs'],
  });
}
</script>

<template>
  <Head
    :title="automation ? $t('Automation history') : $t('Automation runs')"
  />

  <div class="space-y-6">
    <AutomationTabs
      v-if="automation"
      :automation-id="automation.id"
      current="history"
    />

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
      <AppSelect
        v-model="status"
        class="sm:w-48"
        :null-option="$t('Any status')"
        clearable
        :options="statuses"
        :aria-label="$t('Status')"
      />
      <AppInput
        v-model="ticketNumber"
        type="number"
        min="1"
        :placeholder="$t('Ticket number')"
        :aria-label="$t('Ticket number')"
        class="sm:w-48"
      />
    </div>

    <InfiniteScroll v-if="runs.data.length" data="runs">
      <Table>
        <Thead>
          <tr>
            <Th>{{ $t('Time') }}</Th>
            <Th v-if="!automation">{{ $t('Automation') }}</Th>
            <Th>{{ $t('Ticket') }}</Th>
            <Th>{{ $t('Status') }}</Th>
            <Th>{{ $t('Duration') }}</Th>
            <Th right>{{ $t('Depth') }}</Th>
          </tr>
        </Thead>
        <Tbody>
          <tr v-for="run in runs.data" :key="run.id">
            <Td darker>
              <Link
                :href="showRun(run.id).url"
                class="hover:text-primary-600 hover:underline dark:hover:text-primary-400"
              >
                {{ formatDateTime(run.created_at) }}
              </Link>
            </Td>
            <Td v-if="!automation">{{ run.automation_name }}</Td>
            <Td>
              <Link
                v-if="run.ticket_id"
                :href="showTicket(run.ticket_id).url"
                class="hover:text-primary-600 hover:underline dark:hover:text-primary-400"
              >
                #{{ run.ticket_number }} {{ run.ticket_subject }}
              </Link>
              <span v-else class="text-gray-500">{{ $t('None') }}</span>
            </Td>
            <Td>
              <Pill :color="statusColors[run.status]">
                {{ statuses[run.status] }}
              </Pill>
            </Td>
            <Td>{{ runDuration(run) }}</Td>
            <Td class="text-end">{{ run.depth }}</Td>
          </tr>
        </Tbody>
      </Table>

      <template #loading>
        <div class="flex justify-center py-4">
          <Spinner class="size-5 text-gray-400" />
        </div>
      </template>
    </InfiniteScroll>
    <EmptyState
      v-else-if="status || ticketNumber"
      :icon="SearchX"
      :title="$t('No runs match these filters.')"
    />
    <EmptyState v-else :icon="Activity" :title="$t('No runs yet.')">
      {{ $t('Runs are kept for 30 days.') }}
    </EmptyState>
  </div>
</template>
