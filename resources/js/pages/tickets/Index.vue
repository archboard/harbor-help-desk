<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Spinner from '@/components/Spinner.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import PriorityPill from '@/components/tickets/PriorityPill.vue';
import StatusPill from '@/components/tickets/StatusPill.vue';
import TicketFilters from '@/components/tickets/TicketFilters.vue';
import type { TicketQuery } from '@/components/tickets/TicketFilters.vue';
import { formatDateTime, timeAgo } from '@/lib/dates';
import { index, show } from '@/routes/tickets';
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

    <TicketFilters
      :filters="filters"
      :desks="desks"
      :agents="agents"
      :tags="tags"
      :statuses="statuses"
      :priorities="priorities"
      @change="apply"
    />

    <InfiniteScroll v-if="tickets.data.length" data="tickets">
      <Table>
        <Thead>
          <tr>
            <Th>#</Th>
            <Th>Subject</Th>
            <Th>Requester</Th>
            <Th>Desk</Th>
            <Th>Status</Th>
            <Th>Priority</Th>
            <Th>Assignees</Th>
            <Th>Updated</Th>
          </tr>
        </Thead>
        <Tbody>
          <tr v-for="ticket in tickets.data" :key="ticket.id">
            <Td class="whitespace-nowrap text-gray-500 dark:text-gray-400">
              {{ ticket.number }}
            </Td>
            <Td darker class="max-w-md">
              <Link
                :href="show(ticket.id)"
                class="line-clamp-2 hover:text-primary-600 hover:underline dark:hover:text-primary-400"
              >
                {{ ticket.subject }}
              </Link>
            </Td>
            <Td class="whitespace-nowrap">{{ ticket.requester?.name }}</Td>
            <Td class="whitespace-nowrap">{{ ticket.desk?.name }}</Td>
            <Td>
              <StatusPill
                :status="ticket.status"
                :label="ticket.status_label"
              />
            </Td>
            <Td>
              <PriorityPill
                :priority="ticket.priority"
                :label="ticket.priority_label"
              />
            </Td>
            <Td>
              {{
                ticket.assignees?.map((agent) => agent.name).join(', ') || '—'
              }}
            </Td>
            <Td
              class="whitespace-nowrap"
              :title="formatDateTime(ticket.updated_at)"
            >
              {{ timeAgo(ticket.updated_at) }}
            </Td>
          </tr>
        </Tbody>
      </Table>

      <template #loading>
        <div class="flex justify-center py-4">
          <Spinner class="size-5 text-gray-400" />
        </div>
      </template>
    </InfiniteScroll>
    <p v-else class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
      No tickets match these filters.
    </p>
  </div>
</template>
