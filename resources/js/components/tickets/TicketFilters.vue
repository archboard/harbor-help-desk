<script setup lang="ts">
import { Float } from '@headlessui-float/vue';
import { Menu, MenuButton } from '@headlessui/vue';
import { watchDebounced } from '@vueuse/core';
import { Plus, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppInput from '@/components/forms/AppInput.vue';
import AppMenuItem from '@/components/AppMenuItem.vue';
import AppMenuItems from '@/components/AppMenuItems.vue';
import FilterChip from '@/components/tickets/FilterChip.vue';

type QueryValue = string | string[] | undefined;

/** Filters as they go in the URL; `custom_fields` maps a field id to its values. */
export type TicketQuery = Record<
  string,
  QueryValue | Record<string, QueryValue>
>;

type FilterOption = { value: string; label: string };
/** A filter chip: a top-level query key, or a custom field when `fieldId` is set. */
type FilterDefinition = {
  id: string;
  label: string;
  key: string;
  fieldId?: string;
  options: FilterOption[];
};

const { filters, agents, desks, tags, statuses, priorities, customFields } =
  defineProps<{
    filters: TicketQuery;
    desks: App.Data.Desks.DeskData[];
    agents: App.Data.Users.UserData[];
    tags: App.Data.Tags.TagData[];
    statuses: Record<string, string>;
    priorities: Record<string, string>;
    customFields: App.Data.CustomFields.CustomFieldData[];
  }>();

const emit = defineEmits<{ change: [filters: TicketQuery] }>();

function toList(value: unknown): string[] {
  if (value === undefined || value === null || value === '') {
    return [];
  }

  return Array.isArray(value) ? value.map(String) : [String(value)];
}

const search = ref(toList(filters.search)[0] ?? '');

const definitions = computed<FilterDefinition[]>(() => [
  {
    id: 'status',
    label: 'Status',
    key: 'status',
    options: Object.entries(statuses).map(([value, label]) => ({
      value,
      label,
    })),
  },
  {
    id: 'priority',
    label: 'Priority',
    key: 'priority',
    options: Object.entries(priorities).map(([value, label]) => ({
      value,
      label,
    })),
  },
  {
    id: 'desk_id',
    label: 'Desk',
    key: 'desk_id',
    options: desks.map((desk) => ({ value: desk.id, label: desk.name })),
  },
  {
    id: 'assignee_id',
    label: 'Assignee',
    key: 'assignee_id',
    options: [
      { value: 'me', label: 'Me' },
      { value: 'none', label: 'Unassigned' },
      ...agents.map((agent) => ({ value: agent.id, label: agent.name })),
    ],
  },
  ...(tags.length
    ? [
        {
          id: 'tag',
          label: 'Tag',
          key: 'tag',
          options: tags.map((tag) => ({ value: tag.name, label: tag.name })),
        },
      ]
    : []),
  ...customFields.map((field) => ({
    id: `custom_fields.${field.id}`,
    label: field.name,
    key: 'custom_fields',
    fieldId: field.id,
    options:
      field.type === 'checkbox'
        ? [
            { value: '1', label: 'Yes' },
            { value: '0', label: 'No' },
          ]
        : (field.options ?? []).map((option) => ({
            value: option,
            label: option,
          })),
  })),
]);

function customFieldQuery(): Record<string, QueryValue> {
  return (filters.custom_fields ?? {}) as Record<string, QueryValue>;
}

function valuesOf(definition: FilterDefinition): string[] {
  return toList(
    definition.fieldId
      ? customFieldQuery()[definition.fieldId]
      : filters[definition.key],
  );
}

/** Filters added from the menu that have no values yet, so their chip stays visible. */
const added = ref<string[]>([]);
const justAdded = ref<string | null>(null);

const shown = computed(() =>
  definitions.value.filter(
    (definition) =>
      added.value.includes(definition.id) || valuesOf(definition).length,
  ),
);
const addable = computed(() =>
  definitions.value.filter(
    (definition) => !shown.value.some((chip) => chip.id === definition.id),
  ),
);

function setValues(definition: FilterDefinition, values: string[]): void {
  if (definition.fieldId) {
    emit('change', {
      ...filters,
      custom_fields: { ...customFieldQuery(), [definition.fieldId]: values },
    });
  } else {
    emit('change', { ...filters, [definition.key]: values });
  }
}

function add(definition: FilterDefinition): void {
  added.value.push(definition.id);
  justAdded.value = definition.id;
}

function remove(definition: FilterDefinition): void {
  added.value = added.value.filter((id) => id !== definition.id);
  setValues(definition, []);
}

watchDebounced(
  search,
  (value) => emit('change', { ...filters, search: value.trim() }),
  { debounce: 300 },
);

// Quick filters and saved views replace the filters from outside; keep the box in step.
watch(
  () => filters.search,
  (value) => (search.value = toList(value)[0] ?? ''),
);
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <div class="relative w-full sm:w-96">
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

    <FilterChip
      v-for="definition in shown"
      :key="definition.id"
      :label="definition.label"
      :options="definition.options"
      :auto-open="justAdded === definition.id"
      :model-value="valuesOf(definition)"
      @update:model-value="setValues(definition, $event)"
      @remove="remove(definition)"
    />

    <Menu v-if="addable.length" as="div" class="relative inline-block">
      <Float
        placement="bottom-start"
        :offset="4"
        flip
        portal
        enter="transition duration-100 ease-out"
        enter-from="scale-95 opacity-0"
        enter-to="scale-100 opacity-100"
        leave="transition duration-75 ease-in"
        leave-from="scale-100 opacity-100"
        leave-to="scale-95 opacity-0"
      >
        <MenuButton :as="AppButton" color="white">
          <Plus class="mr-1 -ml-1 size-4" aria-hidden="true" />
          Add filter
        </MenuButton>
        <AppMenuItems class="max-h-72 overflow-auto">
          <div class="p-1">
            <AppMenuItem
              v-for="definition in addable"
              :key="definition.id"
              is="button"
              type="button"
              @click="add(definition)"
            >
              {{ definition.label }}
            </AppMenuItem>
          </div>
        </AppMenuItems>
      </Float>
    </Menu>
  </div>
</template>
