<script setup lang="ts">
import { Ticket } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { trans } from 'laravel-vue-i18n';
import { Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import RelativeTime from '@/components/RelativeTime.vue';
import StatusPill from '@/components/tickets/StatusPill.vue';
import AppButton from '@/components/AppButton.vue';
import { create, index, show } from '@/routes/portal/tickets';
import type { Paginated } from '@/types';

defineOptions({ layout: { title: trans('Your tickets') } });

const { filter = null } = defineProps<{
  tickets: Paginated<App.Data.Portal.PortalTicketData>;
  filter?: 'open' | 'resolved' | null;
}>();

const tabs = computed(
  () =>
    [
      { label: trans('All'), value: null },
      { label: trans('Open'), value: 'open' },
      { label: trans('Resolved'), value: 'resolved' },
    ] as const,
);
</script>

<template>
  <Head :title="$t('Your tickets')" />

  <div class="mb-6 flex items-center justify-between gap-4">
    <nav class="flex gap-4 text-sm" :aria-label="$t('Filter tickets')">
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
    <AppButton :component="Link" :href="create()">{{
      $t('New ticket')
    }}</AppButton>
  </div>

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
              <i18n-t keypath="#:number · :desk · Updated :time" tag="span">
                <template #number>{{ ticket.number }}</template>
                <template #desk>{{ ticket.desk_name }}</template>
                <template #time>
                  <RelativeTime :datetime="ticket.updated_at" />
                </template>
              </i18n-t>
            </p>
          </div>
          <StatusPill :status="ticket.status" :label="ticket.status_label" />
        </Link>
      </li>
    </ul>
  </InfiniteScroll>
  <EmptyState v-else :icon="Ticket" :title="$t('No tickets yet.')" />
</template>
