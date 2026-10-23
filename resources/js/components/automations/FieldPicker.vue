<script setup lang="ts">
import {
  Combobox,
  ComboboxButton,
  ComboboxInput,
  ComboboxOption,
  ComboboxOptions,
} from '@headlessui/vue';
import { Check, ChevronsUpDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import { fieldFor } from './summaries';

/**
 * A searchable picker of the fields a rule can test, grouped Ticket, Requester, Message and
 * so on. `groups` limits it to what the trigger makes available.
 */
const {
  catalog,
  groups,
  hasError = false,
} = defineProps<{
  catalog: App.Data.Automations.AutomationCatalogData;
  groups: App.Automations.FieldGroup[];
  hasError?: boolean;
}>();

const model = defineModel<string>({ required: true });

const query = ref('');

const available = computed(() =>
  catalog.field_groups.filter(
    (group) => groups.includes(group.group) && group.fields.length,
  ),
);

const filtered = computed(() => {
  const search = query.value.trim().toLowerCase();

  return available.value
    .map((group) => ({
      ...group,
      fields: group.fields.filter(
        (field) =>
          !search ||
          field.label.toLowerCase().includes(search) ||
          group.label.toLowerCase().includes(search),
      ),
    }))
    .filter((group) => group.fields.length);
});

const display = (key: string): string =>
  key ? (fieldFor(catalog, key)?.label ?? key) : '';
</script>

<template>
  <Combobox
    v-model="model"
    as="div"
    class="relative"
    @update:model-value="query = ''"
  >
    <ComboboxInput
      aria-label="Field"
      placeholder="Choose a field…"
      class="block w-full truncate rounded-lg bg-white pr-8 text-sm shadow-xs focus:ring-2 focus:outline-hidden dark:bg-gray-900"
      :class="
        hasError
          ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500'
          : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600'
      "
      :display-value="(key) => display(key as string)"
      @change="query = ($event.target as HTMLInputElement).value"
    />
    <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-2">
      <ChevronsUpDown class="size-5 text-gray-400" aria-hidden="true" />
    </ComboboxButton>

    <ComboboxOptions
      class="absolute z-10 mt-1 max-h-72 w-full min-w-64 overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-black/5 focus:outline-hidden dark:bg-gray-900"
    >
      <p
        v-if="!filtered.length"
        class="px-4 py-2 text-gray-700 select-none dark:text-gray-400"
      >
        No fields found.
      </p>
      <template v-for="group in filtered" :key="group.group">
        <li
          class="px-4 pt-2 pb-1 text-xs font-semibold tracking-wide text-gray-500 uppercase select-none dark:text-gray-400"
          role="presentation"
        >
          {{ group.label }}
        </li>
        <ComboboxOption
          v-for="field in group.fields"
          :key="field.key"
          v-slot="{ active, selected }"
          as="template"
          :value="field.key"
        >
          <li
            :class="[
              'relative cursor-default py-2 pr-4 pl-10 select-none',
              active
                ? 'bg-primary-600 text-white'
                : 'text-gray-900 dark:text-gray-100',
            ]"
          >
            <span
              class="block truncate"
              :class="selected ? 'font-medium' : 'font-normal'"
            >
              {{ field.label }}
            </span>
            <span
              v-if="selected"
              class="absolute inset-y-0 left-0 flex items-center pl-3"
            >
              <Check class="size-5" aria-hidden="true" />
            </span>
          </li>
        </ComboboxOption>
      </template>
    </ComboboxOptions>
  </Combobox>
</template>
