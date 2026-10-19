<script setup lang="ts">
import { watchDebounced } from '@vueuse/core';
import { Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AppInput from '@/components/forms/AppInput.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import type { Desk, Tag } from '@/types';

export type TicketQuery = Record<string, string | string[] | undefined>;

const { filters, agents } = defineProps<{
  filters: TicketQuery;
  desks: Desk[];
  agents: App.Data.Users.UserData[];
  tags: Tag[];
  statuses: Record<string, string>;
  priorities: Record<string, string>;
}>();

const emit = defineEmits<{ change: [filters: TicketQuery] }>();

/** The selects show one value; a list (e.g. from a saved view) shows its first. */
function single(value: string | string[] | undefined): string | null {
  return (Array.isArray(value) ? value[0] : value) ?? null;
}

const search = ref(single(filters.search) ?? '');

const assigneeOptions = computed(() => [
  { value: 'me', label: 'Me' },
  { value: 'none', label: 'Unassigned' },
  ...agents.map((agent) => ({ value: agent.id, label: agent.name })),
]);

function set(
  key: string,
  value: string | number | boolean | null | undefined,
): void {
  emit('change', { ...filters, [key]: value ? String(value) : undefined });
}

watchDebounced(search, (value) => set('search', value.trim()), {
  debounce: 300,
});

// Quick filters and saved views replace the filters from outside; keep the box in step.
watch(
  () => filters.search,
  (value) => (search.value = single(value) ?? ''),
);
</script>

<template>
  <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
    <div class="relative sm:col-span-2">
      <Search
        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400"
        aria-hidden="true"
      />
      <AppInput
        v-model="search"
        type="search"
        placeholder="Search subject, messages, #number or requester"
        aria-label="Search tickets"
        class="pl-9"
      />
    </div>
    <AppSelect
      :model-value="single(filters.status)"
      :options="statuses"
      clearable
      null-option="Any status"
      aria-label="Status"
      @update:model-value="set('status', $event)"
    />
    <AppSelect
      :model-value="single(filters.priority)"
      :options="priorities"
      clearable
      null-option="Any priority"
      aria-label="Priority"
      @update:model-value="set('priority', $event)"
    />
    <AppSelect
      :model-value="single(filters.desk_id)"
      :options="desks"
      clearable
      null-option="Any desk"
      aria-label="Desk"
      @update:model-value="set('desk_id', $event)"
    />
    <AppSelect
      :model-value="single(filters.assignee_id)"
      :options="assigneeOptions"
      clearable
      null-option="Anyone"
      aria-label="Assignee"
      @update:model-value="set('assignee_id', $event)"
    />
    <AppSelect
      v-if="tags.length"
      :model-value="single(filters.tag)"
      :options="tags.map((tag) => tag.name)"
      clearable
      null-option="Any tag"
      aria-label="Tag"
      class="lg:col-start-6"
      @update:model-value="set('tag', $event)"
    />
  </div>
</template>
