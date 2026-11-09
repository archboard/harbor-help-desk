<script setup lang="ts">
import { ArrowDown, ArrowUp } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { formatValue } from '@/lib/reports';

type Row = App.Data.Reports.AgentRowData;
type Column = {
  key: Exclude<keyof Row, 'agent'> | 'name';
  label: string;
  kind?: 'count' | 'minutes';
};

const { data } = defineProps<{ data: App.Data.Reports.AgentsReportData }>();

const columns = computed<Column[]>(() => [
  { key: 'name', label: trans('Agent') },
  { key: 'public_replies', label: trans('Public replies'), kind: 'count' },
  { key: 'internal_notes', label: trans('Internal notes'), kind: 'count' },
  { key: 'tickets_solved', label: trans('Solved'), kind: 'count' },
  { key: 'open_assigned', label: trans('Open assigned'), kind: 'count' },
  { key: 'median_reply', label: trans('Median reply'), kind: 'minutes' },
  {
    key: 'median_first_response',
    label: trans('Median first response'),
    kind: 'minutes',
  },
]);

const sortKey = ref<Column['key']>('name');
const ascending = ref(true);

const value = (row: Row, key: Column['key']) =>
  key === 'name' ? row.agent.name : row[key];

// Empty medians sort last either way.
const rows = computed(() =>
  [...data.agents].sort((a, b) => {
    const [left, right] = [value(a, sortKey.value), value(b, sortKey.value)];

    if (left === null || right === null) {
      return left === right ? 0 : left === null ? 1 : -1;
    }

    const order =
      typeof left === 'string'
        ? left.localeCompare(String(right))
        : left - Number(right);

    return ascending.value ? order : -order;
  }),
);

function sortBy(key: Column['key']): void {
  ascending.value = sortKey.value === key ? !ascending.value : key === 'name';
  sortKey.value = key;
}
</script>

<template>
  <Table>
    <Thead>
      <tr>
        <Th
          v-for="column in columns"
          :key="column.key"
          :right="column.key !== 'name'"
          :aria-sort="
            sortKey === column.key
              ? ascending
                ? 'ascending'
                : 'descending'
              : 'none'
          "
        >
          <button
            type="button"
            class="inline-flex items-center gap-1"
            @click="sortBy(column.key)"
          >
            {{ column.label }}
            <component
              :is="ascending ? ArrowUp : ArrowDown"
              v-if="sortKey === column.key"
              class="size-3"
              aria-hidden="true"
            />
          </button>
        </Th>
      </tr>
    </Thead>
    <Tbody>
      <tr v-for="row in rows" :key="row.agent.id">
        <Td darker>{{ row.agent.name }}</Td>
        <Td
          v-for="column in columns.slice(1)"
          :key="column.key"
          class="text-end tabular-nums"
        >
          {{
            formatValue(value(row, column.key) as number | null, column.kind!)
          }}
        </Td>
      </tr>
      <tr v-if="rows.length === 0">
        <Td :colspan="columns.length">{{ $t('No agents on these desks.') }}</Td>
      </tr>
    </Tbody>
  </Table>
</template>
