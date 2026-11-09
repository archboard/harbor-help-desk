<script setup lang="ts">
import { InfiniteScroll, Link } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Eye } from '@lucide/vue';
import { computed } from 'vue';
import AppCheckbox from '@/components/forms/AppCheckbox.vue';
import Spinner from '@/components/Spinner.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import ColumnPicker from '@/components/tickets/ColumnPicker.vue';
import PriorityPill from '@/components/tickets/PriorityPill.vue';
import StatusPill from '@/components/tickets/StatusPill.vue';
import RelativeTime from '@/components/RelativeTime.vue';
import { formatDate } from '@/lib/dates';
import {
  availableColumns,
  customFieldColumnId,
  DEFAULT_COLUMNS,
  formatCustomField,
} from '@/lib/ticketColumns';
import type { TicketColumn, TicketSort } from '@/lib/ticketColumns';
import { show } from '@/routes/tickets';

/**
 * A page of tickets that loads more as it scrolls. `prop` is the name of the
 * `Inertia::scroll()` prop holding them. Binding `v-model:selected` adds checkboxes, and
 * binding `v-model:columns` adds a column picker to the header. `v-model:focusedId` is the
 * keyboard-focused row; clicking a row focuses it. Binding `v-model:sort` makes the sortable
 * columns' headers clickable.
 */
const {
  tickets,
  hideRequester = false,
  customFields = [],
  previewable = false,
  flashingIds = [],
} = defineProps<{
  prop: string;
  tickets: App.Data.Tickets.TicketData[];
  hideRequester?: boolean;
  customFields?: App.Data.CustomFields.CustomFieldData[];
  /** Adds an eye button to each row that emits `preview`. */
  previewable?: boolean;
  /** Rows that play a brief highlight, e.g. tickets that just arrived live. */
  flashingIds?: string[];
}>();

/** Ids of the columns to show; unknown ids (e.g. deleted custom fields) are dropped. */
const columns = defineModel<string[]>('columns');

const shown = computed(() =>
  availableColumns(customFields).filter(
    (column) =>
      (columns.value ?? DEFAULT_COLUMNS).includes(column.id) &&
      !(hideRequester && column.id === 'requester'),
  ),
);

function customFieldText(
  columnId: string,
  ticket: App.Data.Tickets.TicketData,
): string {
  const field = customFields.find(
    (candidate) => customFieldColumnId(candidate) === columnId,
  );

  return field
    ? formatCustomField(field, ticket.custom_fields?.[field.id])
    : '';
}

const emit = defineEmits<{ preview: [id: string] }>();

const sort = defineModel<TicketSort>('sort');

/** Clicking the sorted column flips it; any other starts in its own default direction. */
function sortBy(column: TicketColumn): void {
  sort.value =
    sort.value?.sort === column.sort!.sort
      ? {
          ...column.sort!,
          direction: sort.value.direction === 'asc' ? 'desc' : 'asc',
        }
      : column.sort;
}

function ariaSort(column: TicketColumn): 'ascending' | 'descending' | 'none' {
  if (sort.value?.sort !== column.sort?.sort) {
    return 'none';
  }

  return sort.value?.direction === 'asc' ? 'ascending' : 'descending';
}

const selected = defineModel<string[]>('selected');
const focusedId = defineModel<string>('focusedId');

/** Selects or clears every ticket loaded so far. */
const allSelected = computed({
  get: () =>
    tickets.length > 0 &&
    tickets.every((ticket) => selected.value?.includes(ticket.id)),
  set: (all: boolean) =>
    (selected.value = all ? tickets.map((ticket) => ticket.id) : []),
});
</script>

<template>
  <InfiniteScroll :data="prop">
    <Table>
      <Thead>
        <tr>
          <Th v-if="selected" class="w-0 pr-0">
            <AppCheckbox v-model="allSelected">
              <span class="sr-only">{{ $t('Select all loaded tickets') }}</span>
            </AppCheckbox>
          </Th>
          <template v-for="column in shown" :key="column.id">
            <Th v-if="sort && column.sort" :aria-sort="ariaSort(column)">
              <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-1"
                @click="sortBy(column)"
              >
                {{ column.label }}
                <component
                  :is="sort.direction === 'asc' ? ArrowUp : ArrowDown"
                  v-if="sort.sort === column.sort.sort"
                  class="size-3"
                  aria-hidden="true"
                />
              </button>
            </Th>
            <Th v-else>{{ column.label }}</Th>
          </template>
          <Th v-if="columns || previewable" class="w-0" right>
            <ColumnPicker
              v-if="columns"
              v-model="columns"
              :available="availableColumns(customFields)"
            />
          </Th>
        </tr>
      </Thead>
      <Tbody>
        <tr
          v-for="ticket in tickets"
          :key="ticket.id"
          :data-ticket-id="ticket.id"
          :aria-selected="focusedId === ticket.id"
          class="whitespace-nowrap"
          :class="[
            focusedId === ticket.id &&
              'bg-primary-50/50 dark:bg-primary-950/30 [&>td:first-child]:shadow-[inset_3px_0_0_var(--color-primary-500)]',
            flashingIds.includes(ticket.id) && 'animate-row-flash',
          ]"
          @click="focusedId = ticket.id"
        >
          <Td v-if="selected" class="w-0 pr-0">
            <AppCheckbox v-model="selected" :value="ticket.id">
              <span class="sr-only">{{
                $t('Select #:number', { number: String(ticket.number) })
              }}</span>
            </AppCheckbox>
          </Td>
          <template v-for="column in shown" :key="column.id">
            <Td
              v-if="column.id === 'number'"
              class="text-gray-500 dark:text-gray-400"
            >
              {{ ticket.number }}
            </Td>
            <Td v-else-if="column.id === 'subject'" darker>
              <Link
                :href="show(ticket.id)"
                :title="ticket.subject"
                class="block max-w-md truncate hover:text-primary-600 hover:underline dark:hover:text-primary-400"
              >
                {{ ticket.subject }}
              </Link>
            </Td>
            <Td v-else-if="column.id === 'requester'">
              {{ ticket.requester?.name }}
            </Td>
            <Td v-else-if="column.id === 'desk'">
              {{ ticket.desk?.name }}
            </Td>
            <Td v-else-if="column.id === 'status'">
              <StatusPill
                :status="ticket.status"
                :label="ticket.status_label"
              />
            </Td>
            <Td v-else-if="column.id === 'priority'">
              <PriorityPill
                :priority="ticket.priority"
                :label="ticket.priority_label"
              />
            </Td>
            <Td v-else-if="column.id === 'assignees'">
              {{
                ticket.assignees?.map((agent) => agent.name).join(', ') || '—'
              }}
            </Td>
            <Td v-else-if="column.id === 'tags'">
              {{ ticket.tags?.map((tag) => tag.name).join(', ') || '—' }}
            </Td>
            <Td v-else-if="column.id === 'due'">
              {{ formatDate(ticket.due_at) }}
            </Td>
            <Td v-else-if="column.id === 'created'">
              <RelativeTime :datetime="ticket.created_at" />
            </Td>
            <Td v-else-if="column.id === 'updated'">
              <RelativeTime :datetime="ticket.updated_at" />
            </Td>
            <Td v-else>
              {{ customFieldText(column.id, ticket) }}
            </Td>
          </template>
          <Td v-if="columns || previewable" class="w-0">
            <button
              v-if="previewable"
              type="button"
              class="rounded-md p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
              @click.stop="emit('preview', ticket.id)"
            >
              <Eye class="size-4" aria-hidden="true" />
              <span class="sr-only">{{
                $t('Quick view #:number', { number: String(ticket.number) })
              }}</span>
            </button>
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
