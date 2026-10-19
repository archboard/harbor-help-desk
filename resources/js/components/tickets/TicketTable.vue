<script setup lang="ts">
import { InfiniteScroll, Link } from '@inertiajs/vue3';
import Spinner from '@/components/Spinner.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import PriorityPill from '@/components/tickets/PriorityPill.vue';
import StatusPill from '@/components/tickets/StatusPill.vue';
import { formatDateTime, timeAgo } from '@/lib/dates';
import { show } from '@/routes/tickets';
import type { Ticket } from '@/types';

/**
 * A page of tickets that loads more as it scrolls. `prop` is the name of the
 * `Inertia::scroll()` prop holding them.
 */
const { hideRequester = false } = defineProps<{
  prop: string;
  tickets: Ticket[];
  hideRequester?: boolean;
}>();
</script>

<template>
  <InfiniteScroll :data="prop">
    <Table>
      <Thead>
        <tr>
          <Th>#</Th>
          <Th>Subject</Th>
          <Th v-if="!hideRequester">Requester</Th>
          <Th>Desk</Th>
          <Th>Status</Th>
          <Th>Priority</Th>
          <Th>Assignees</Th>
          <Th>Updated</Th>
        </tr>
      </Thead>
      <Tbody>
        <tr v-for="ticket in tickets" :key="ticket.id">
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
          <Td v-if="!hideRequester" class="whitespace-nowrap">
            {{ ticket.requester?.name }}
          </Td>
          <Td class="whitespace-nowrap">{{ ticket.desk?.name }}</Td>
          <Td>
            <StatusPill :status="ticket.status" :label="ticket.status_label" />
          </Td>
          <Td>
            <PriorityPill
              :priority="ticket.priority"
              :label="ticket.priority_label"
            />
          </Td>
          <Td>
            {{ ticket.assignees?.map((agent) => agent.name).join(', ') || '—' }}
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
</template>
