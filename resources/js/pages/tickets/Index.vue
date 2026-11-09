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
import {
  builtInColumns,
  DEFAULT_COLUMNS,
  DEFAULT_SORT,
} from '@/lib/ticketColumns';
import type { TicketSort } from '@/lib/ticketColumns';
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

/** Tickets that just arrived over the socket, highlighted once their row appears. */
const flashingIds = ref<string[]>([]);

useLiveReload({
  channels: desks.map((desk) => `desks.${desk.id}`),
  only: ['tickets'],
  reset: ['tickets'],
  onPing: ({ ticket_id, type }) => {
    if (type !== 'created' || !ticket_id) {
      return;
    }

    flashingIds.value.push(ticket_id);
    // ponytail: outlasts the reload plus the 2s animation; the row mounts with the class and plays it once
    setTimeout(() => {
      flashingIds.value = flashingIds.value.filter((id) => id !== ticket_id);
    }, 5000);
  },
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

/** The filters without the sort, which the user's stored preference owns. */
function withoutSort(query: TicketQuery): TicketQuery {
  return Object.fromEntries(
    Object.entries(query).filter(
      ([key]) => !['sort', 'direction'].includes(key),
    ),
  );
}

const listFilters = computed(() => withoutSort(filters));

/** The quick filter that matches the current filters exactly, if any. */
const activeQuickFilter = computed(
  () =>
    quickFilters.value.find((quick) =>
      sameQuery(quick.query, listFilters.value),
    )?.label,
);

/** A query's sort, falling back to the defaults for anything a column doesn't offer. */
function sortOf(query: Record<string, unknown>): TicketSort {
  const column = builtInColumns().find(
    (candidate) => candidate.sort && candidate.sort.sort === query.sort,
  );

  if (!column?.sort) {
    return DEFAULT_SORT;
  }

  return {
    sort: column.sort.sort,
    direction:
      query.direction === 'asc' || query.direction === 'desc'
        ? query.direction
        : column.sort.direction,
  };
}

const storedSort = useLocalStorage<TicketSort>(
  'harbor.ticket-sort',
  DEFAULT_SORT,
);
/** The URL decides what's shown; picking a sort also remembers it for next time. */
const sort = computed({
  get: () => sortOf(filters),
  set: (value: TicketSort) => {
    storedSort.value = value;
    apply(filters, value);
  },
});

onMounted(() => {
  const remembered = sortOf(storedSort.value ?? {});

  if (
    !filters.sort &&
    (remembered.sort !== DEFAULT_SORT.sort ||
      remembered.direction !== DEFAULT_SORT.direction)
  ) {
    apply(filters, remembered);
  }
});

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

/** Keeps the current sort unless the query (e.g. a saved view) brings its own. */
function apply(
  query: TicketQuery,
  sorted: TicketSort = query.sort ? sortOf(query) : sort.value,
): void {
  selected.value = [];
  const isDefault =
    sorted.sort === DEFAULT_SORT.sort &&
    sorted.direction === DEFAULT_SORT.direction;

  router.get(
    index.url(),
    cleanQuery({ ...withoutSort(query), ...(isDefault ? {} : sorted) }),
    {
      only: ['tickets', 'filters'],
      reset: ['tickets'],
      preserveState: true,
      preserveScroll: true,
      replace: true,
    },
  );
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
          :filters="cleanQuery(listFilters)"
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
      v-model:sort="sort"
      v-model:focused-id="focusedId"
      :custom-fields="customFields"
      :flashing-ids="flashingIds"
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
