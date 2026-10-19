<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import { computed } from 'vue';
import TicketFilters from '@/components/tickets/TicketFilters.vue';
import TicketTable from '@/components/tickets/TicketTable.vue';
import type { TicketQuery } from '@/components/tickets/TicketFilters.vue';
import { create, index } from '@/routes/tickets';
import type { Agent, Desk, Paginated, Tag, Ticket } from '@/types';

defineOptions({ layout: { title: 'Tickets' } });

const { filters } = defineProps<{
  tickets: Paginated<Ticket>;
  filters: TicketQuery;
  desks: Desk[];
  agents: Agent[];
  tags: Tag[];
  statuses: Record<string, string>;
  priorities: Record<string, string>;
}>();

const quickFilters: Array<{ label: string; query: TicketQuery }> = [
  { label: 'All', query: {} },
  { label: 'My tickets', query: { assignee_id: 'me' } },
  { label: 'Unassigned', query: { assignee_id: 'none' } },
  { label: 'All open', query: { status: 'open' } },
  { label: 'Deleted', query: { deleted: '1' } },
];

/** The quick filter that matches the current filters exactly, if any. */
const activeQuickFilter = computed(() => {
  const current = JSON.stringify(clean(filters));

  return quickFilters.find(
    (quick) => JSON.stringify(clean(quick.query)) === current,
  )?.label;
});

/** Drop empty values so they don't end up in the URL. */
function clean(query: TicketQuery): TicketQuery {
  return Object.fromEntries(
    Object.entries(query)
      .filter(([, value]) => value !== undefined && value !== '')
      .sort(([a], [b]) => a.localeCompare(b)),
  );
}

function apply(query: TicketQuery): void {
  router.get(index.url(), clean(query), {
    only: ['tickets', 'filters'],
    reset: ['tickets'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}
</script>

<template>
  <Head title="Tickets" />

  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <nav class="flex flex-wrap gap-1" aria-label="Quick filters">
        <button
          v-for="quick in quickFilters"
          :key="quick.label"
          type="button"
          :aria-pressed="activeQuickFilter === quick.label"
          :class="[
            'rounded-md px-3 py-1.5 text-sm font-medium',
            activeQuickFilter === quick.label
              ? 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'
              : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white',
          ]"
          @click="apply(quick.query)"
        >
          {{ quick.label }}
        </button>
      </nav>
      <AppButton :component="Link" :href="create()">New ticket</AppButton>
    </div>

    <TicketFilters
      :filters="filters"
      :desks="desks"
      :agents="agents"
      :tags="tags"
      :statuses="statuses"
      :priorities="priorities"
      @change="apply"
    />

    <TicketTable
      v-if="tickets.data.length"
      prop="tickets"
      :tickets="tickets.data"
    />
    <p v-else class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
      No tickets match these filters.
    </p>
  </div>
</template>
