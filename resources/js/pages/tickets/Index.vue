<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import { useLocalStorage } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import SavedViews from '@/components/tickets/SavedViews.vue';
import TicketBulkActions from '@/components/tickets/TicketBulkActions.vue';
import TicketFilters from '@/components/tickets/TicketFilters.vue';
import TicketTable from '@/components/tickets/TicketTable.vue';
import type { TicketQuery } from '@/components/tickets/TicketFilters.vue';
import { useLiveReload } from '@/composables/useLiveReload';
import { useRowNavigation } from '@/composables/useRowNavigation';
import { paletteTarget } from '@/lib/commandPalette';
import { openQuickView } from '@/lib/quickView';
import { DEFAULT_COLUMNS } from '@/lib/ticketColumns';
import { cleanQuery, sameQuery } from '@/lib/tickets';
import { claim, index } from '@/routes/tickets';
import type { Paginated } from '@/types';

defineOptions({ layout: { title: trans('Tickets') }, inheritAttrs: false });

const { tickets, filters, leadDeskIds, desks, customFields } = defineProps<{
  tickets: Paginated<App.Data.Tickets.TicketData>;
  /** Desks whose tickets the user may delete. */
  leadDeskIds: string[];
  filters: TicketQuery;
  savedViews: App.Data.SavedViews.SavedViewData[];
  desks: App.Data.Desks.DeskData[];
  agents: App.Data.Users.UserData[];
  tags: App.Data.Tags.TagData[];
  customFields: App.Data.CustomFields.CustomFieldData[];
  statuses: Record<string, string>;
  priorities: Record<string, string>;
}>();

useLiveReload({
  channels: desks.map((desk) => `desks.${desk.id}`),
  only: ['tickets'],
  reset: ['tickets'],
});

const quickFilters = computed<Array<{ label: string; query: TicketQuery }>>(
  () => [
    { label: trans('All'), query: {} },
    { label: trans('My tickets'), query: { assignee_id: 'me' } },
    { label: trans('Unassigned'), query: { assignee_id: 'none' } },
    { label: trans('All open'), query: { status: 'open' } },
    { label: trans('Deleted'), query: { deleted: '1' } },
  ],
);

/** The quick filter that matches the current filters exactly, if any. */
const activeQuickFilter = computed(
  () =>
    quickFilters.value.find((quick) => sameQuery(quick.query, filters))?.label,
);

const storedColumns = useLocalStorage<string[]>(
  'harbor.ticket-columns',
  DEFAULT_COLUMNS,
);
/** Falls back to the defaults when storage holds something other than a list. */
const columns = computed({
  get: () =>
    Array.isArray(storedColumns.value) ? storedColumns.value : DEFAULT_COLUMNS,
  set: (value: string[]) => (storedColumns.value = value),
});

const selected = ref<string[]>([]);
const focusedId = ref<string>();

useRowNavigation({
  ids: () => tickets.data.map((ticket) => ticket.id),
  focusedId,
  selected,
  canSelect: () => !filters.deleted,
  onPreview: openQuickView,
});

/** `Cmd+K` acts on the selection when the focused row is in it, otherwise on the focused row. */
onMounted(() => {
  paletteTarget.value = () => {
    const ticket = tickets.data.find((row) => row.id === focusedId.value);

    if (!ticket) {
      return null;
    }

    const ticketIds = selected.value.includes(ticket.id)
      ? selected.value
      : [ticket.id];

    return {
      ticketIds,
      ticket: ticketIds.length === 1 ? ticket : undefined,
      done: () => (selected.value = []),
    };
  };
});
onBeforeUnmount(() => (paletteTarget.value = null));

/** Mirrors `TicketPolicy@delete` for every selected ticket. */
const canDeleteSelected = computed(() =>
  tickets.data
    .filter((ticket) => selected.value.includes(ticket.id))
    .every((ticket) => leadDeskIds.includes(ticket.desk_id)),
);

function apply(query: TicketQuery): void {
  selected.value = [];

  router.get(index.url(), cleanQuery(query), {
    only: ['tickets', 'filters'],
    reset: ['tickets'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}
</script>

<template>
  <div class="space-y-4">
    <Head :title="$t('Tickets')" />

    <div class="flex flex-wrap items-center justify-between gap-3">
      <nav class="flex flex-wrap gap-1" :aria-label="$t('Quick filters')">
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
      <div class="flex flex-wrap items-center gap-2">
        <SavedViews
          :views="savedViews"
          :filters="cleanQuery(filters)"
          @apply="apply"
        />
        <AppButton color="white" @click="router.post(claim.url())">
          {{ $t('Get next ticket') }}
        </AppButton>
      </div>
    </div>

    <TicketFilters
      :filters="filters"
      :desks="desks"
      :agents="agents"
      :tags="tags"
      :custom-fields="customFields"
      :statuses="statuses"
      :priorities="priorities"
      @change="apply"
    />

    <TicketTable
      v-if="tickets.data.length"
      :selected="filters.deleted ? undefined : selected"
      prop="tickets"
      :tickets="tickets.data"
      v-model:columns="columns"
      v-model:focused-id="focusedId"
      :custom-fields="customFields"
      @update:selected="selected = $event ?? []"
      previewable
      @preview="openQuickView"
    />
    <p v-else class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
      {{ $t('No tickets match these filters.') }}
    </p>

    <TicketBulkActions
      v-if="selected.length"
      :ticket-ids="selected"
      :can-delete="canDeleteSelected"
      :desks="desks"
      :agents="agents"
      :statuses="statuses"
      :priorities="priorities"
      @clear="selected = []"
    />
  </div>
</template>
