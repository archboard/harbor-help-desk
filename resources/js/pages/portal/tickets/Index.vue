<script setup lang="ts">
import { Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import RelativeTime from '@/components/RelativeTime.vue';
import StatusPill from '@/components/tickets/StatusPill.vue';
import { index, show } from '@/routes/portal/tickets';
import type { Paginated } from '@/types';

defineOptions({ layout: { title: 'Your tickets' } });

const { filter = null } = defineProps<{
  tickets: Paginated<App.Data.Portal.PortalTicketData>;
  filter?: 'open' | 'resolved' | null;
}>();

const tabs = [
  { label: 'All', value: null },
  { label: 'Open', value: 'open' },
  { label: 'Resolved', value: 'resolved' },
] as const;
</script>

<template>
  <Head title="Your tickets" />

  <nav class="mb-6 flex gap-4 text-sm" aria-label="Filter tickets">
    <Link
      v-for="tab in tabs"
      :key="tab.label"
      :href="index({ query: tab.value ? { status: tab.value } : {} })"
      :class="
        filter === tab.value
          ? 'font-semibold text-primary-600 dark:text-primary-400'
          : 'text-gray-600 hover:underline dark:text-gray-400'
      "
    >
      {{ tab.label }}
    </Link>
  </nav>

  <InfiniteScroll v-if="tickets.data.length" data="tickets">
    <ul
      class="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900"
    >
      <li v-for="ticket in tickets.data" :key="ticket.id">
        <Link
          :href="show(ticket.id)"
          class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50"
        >
          <div class="min-w-0">
            <p class="truncate font-medium">
              {{ ticket.subject }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              #{{ ticket.number }} · {{ ticket.desk_name }} · Updated
              <RelativeTime :datetime="ticket.updated_at" />
            </p>
          </div>
          <StatusPill :status="ticket.status" :label="ticket.status_label" />
        </Link>
      </li>
    </ul>
  </InfiniteScroll>
  <p v-else class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
    No tickets yet.
  </p>
</template>
