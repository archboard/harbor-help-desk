<script setup lang="ts">
import { Float } from '@headlessui-float/vue';
import {
  Listbox,
  ListboxButton,
  ListboxOption,
  ListboxOptions,
} from '@headlessui/vue';
import { Check, ChevronDown, X } from '@lucide/vue';
import { computed, onMounted, useTemplateRef } from 'vue';

/**
 * One active filter: a chip naming the filter and its chosen values, opening a multi-select
 * list. Removing the chip clears the filter.
 */
const {
  label,
  options,
  autoOpen = false,
} = defineProps<{
  label: string;
  options: { value: string; label: string }[];
  /** Open the list on mount, for a filter that was just added and has no values yet. */
  autoOpen?: boolean;
}>();

const values = defineModel<string[]>({ required: true });
const emit = defineEmits<{ remove: [] }>();

const button = useTemplateRef<{ $el: HTMLElement }>('button');

const summary = computed(() => {
  const names = values.value.map(
    (value) => options.find((option) => option.value === value)?.label ?? value,
  );

  return names.length > 2 ? `${names.length} selected` : names.join(', ');
});

onMounted(() => {
  if (autoOpen) {
    button.value?.$el.click();
  }
});
</script>

<template>
  <div
    class="inline-flex items-center rounded-lg border border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800"
  >
    <Listbox v-model="values" multiple as="div" class="relative">
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
        <ListboxButton
          ref="button"
          class="inline-flex items-center gap-1 rounded-l-lg px-3 py-2 hover:bg-gray-50 focus:ring-2 focus:ring-primary-500 focus:outline-hidden dark:hover:bg-gray-700"
        >
          <span class="font-medium">{{ label }}</span>
          <span v-if="summary" class="text-gray-600 dark:text-gray-300">
            {{ summary }}
          </span>
          <ChevronDown class="size-3.5" aria-hidden="true" />
        </ListboxButton>
        <!-- Same look as AppMenuItems, which is tied to a headless Menu. -->
        <ListboxOptions
          class="max-h-72 w-56 overflow-auto rounded-md bg-white p-1 shadow-lg ring-1 ring-black/5 focus:outline-hidden dark:bg-gray-800 dark:ring-white/10"
        >
          <ListboxOption
            v-for="option in options"
            :key="option.value"
            v-slot="{ active, selected }"
            :value="option.value"
            as="template"
          >
            <li
              :class="[
                active
                  ? 'bg-gray-500 text-white'
                  : 'text-black dark:text-gray-100',
                'flex cursor-pointer items-center justify-between rounded-sm px-4 py-2 text-sm',
              ]"
            >
              {{ option.label }}
              <Check v-if="selected" class="size-4" aria-hidden="true" />
            </li>
          </ListboxOption>
        </ListboxOptions>
      </Float>
    </Listbox>
    <button
      type="button"
      class="rounded-r-lg border-l border-gray-300 px-2 py-2 hover:text-red-600 focus:ring-2 focus:ring-primary-500 focus:outline-hidden dark:border-gray-600"
      @click="emit('remove')"
    >
      <span class="sr-only">Remove the {{ label }} filter</span>
      <X class="size-3.5" aria-hidden="true" />
    </button>
  </div>
</template>
