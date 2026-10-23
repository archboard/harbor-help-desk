<script
  setup
  lang="ts"
  generic="T extends Record<string, unknown> = Record<string, unknown>"
>
import {
  Combobox,
  ComboboxButton,
  ComboboxInput,
  ComboboxOption,
  ComboboxOptions,
} from '@headlessui/vue';
import { Check, ChevronsUpDown, Trash2 } from '@lucide/vue';
import Spinner from '@/components/Spinner.vue';

const {
  options,
  displayValue,
  hasError = false,
  id,
  optionKey = 'id',
  placeholder = 'Type for options…',
  loading = false,
  disabled = false,
} = defineProps<{
  options: T[];
  displayValue?: (item: T | null) => string;
  hasError?: boolean;
  id?: string;
  optionKey?: string;
  placeholder?: string;
  loading?: boolean;
  disabled?: boolean;
}>();

const model = defineModel<T | null>({ default: null });
const query = defineModel<string>('query');

const display = (item: unknown): string => {
  if (displayValue) {
    return displayValue(item as T | null) || '';
  }

  const option = item as Record<string, unknown> | null;

  return String(option?.name ?? option?.title ?? option?.label ?? '');
};
</script>

<template>
  <Combobox v-model="model" :disabled="disabled" nullable>
    <div class="relative w-full">
      <ComboboxInput
        :id="id"
        class="block w-full truncate rounded-lg bg-white pr-14 text-sm shadow-xs focus:ring-2 focus:outline-hidden dark:bg-gray-900"
        :class="
          hasError
            ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500 dark:text-red-300 dark:placeholder-red-400/60'
            : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600'
        "
        :display-value="display"
        :placeholder="placeholder"
        @change="query = ($event.target as HTMLInputElement).value"
      />

      <button
        v-if="model && !disabled"
        type="button"
        class="absolute inset-y-0 right-8 flex cursor-pointer items-center"
        @click.prevent="model = null"
      >
        <Trash2 class="size-4 text-red-400" aria-hidden="true" />
        <span class="sr-only">Clear selection</span>
      </button>

      <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-2">
        <ChevronsUpDown class="size-5 text-gray-400" aria-hidden="true" />
      </ComboboxButton>

      <Transition
        enter-active-class="transition duration-100 ease-out"
        enter-from-class="scale-95 opacity-0"
        enter-to-class="scale-100 opacity-100"
        leave-active-class="transition duration-75 ease-out"
        leave-from-class="scale-100 opacity-100"
        leave-to-class="scale-95 opacity-0"
      >
        <ComboboxOptions
          class="absolute z-10 mt-1 max-h-60 w-full min-w-64 overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black/5 focus:outline-hidden sm:text-sm dark:bg-gray-900"
        >
          <div
            v-if="options.length === 0 && query && loading"
            class="flex items-center justify-center px-4 py-2"
          >
            <Spinner class="size-5 text-gray-500" />
          </div>
          <div
            v-else-if="options.length === 0 && query"
            class="px-4 py-2 text-gray-700 select-none dark:text-gray-400"
          >
            Nothing found.
          </div>

          <ComboboxOption
            v-for="item in options"
            :key="String(item[optionKey])"
            v-slot="{ active, selected }"
            as="template"
            :value="item"
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
                <slot
                  name="item"
                  :item="item"
                  :active="active"
                  :selected="selected"
                >
                  {{ display(item) }}
                </slot>
              </span>
              <span
                v-if="selected"
                class="absolute inset-y-0 left-0 flex items-center pl-3"
              >
                <Check class="size-5" aria-hidden="true" />
              </span>
            </li>
          </ComboboxOption>
        </ComboboxOptions>
      </Transition>
    </div>
  </Combobox>
</template>
