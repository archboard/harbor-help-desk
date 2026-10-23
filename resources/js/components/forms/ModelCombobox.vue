<script
  setup
  lang="ts"
  generic="T extends { id: string; [key: string]: unknown }"
>
import { watchDebounced } from '@vueuse/core';
import { computed, ref, shallowRef } from 'vue';
import AppCombobox from '@/components/forms/AppCombobox.vue';

const {
  url,
  hasError = false,
  disabled = false,
  id,
  displayValue,
  minLength = 3,
} = defineProps<{
  /** JSON endpoint that takes `?search=` and returns a list of resources with an `id`. */
  url: string;
  hasError?: boolean;
  disabled?: boolean;
  id?: string;
  /** How to show an option; defaults to its `name`, `title` or `label`. */
  displayValue?: (item: T | null) => string;
  /** How many characters to type before searching. */
  minLength?: number;
}>();

const model = defineModel<string | null>();
// ponytail: pass v-model:object to preselect; PTC re-searched by id on mount, which needs id lookup on every endpoint
const object = defineModel<T | null>('object', { default: null });

const selected = computed({
  get: () => object.value,
  set: (value: T | null) => {
    object.value = value;
    model.value = value?.id ?? null;
  },
});

const query = ref('');
const options = shallowRef<T[]>([]);
const loading = ref(false);
let controller: AbortController | null = null;

async function search(term: string): Promise<void> {
  controller?.abort();
  controller = new AbortController();
  loading.value = true;

  try {
    const response = await fetch(
      `${url}?${new URLSearchParams({ search: term })}`,
      {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        signal: controller.signal,
      },
    );

    options.value = response.ok ? await response.json() : [];
  } catch (error) {
    if ((error as Error).name === 'AbortError') {
      return;
    }

    options.value = [];
  }

  loading.value = false;
}

watchDebounced(
  query,
  (term) => {
    if (term.length >= minLength) {
      void search(term);
    }
  },
  { debounce: 500 },
);
</script>

<template>
  <AppCombobox
    :id="id"
    v-model="selected"
    v-model:query="query"
    :options="options"
    :has-error="hasError"
    :disabled="disabled"
    :loading="loading"
    :display-value="displayValue"
  />
</template>
