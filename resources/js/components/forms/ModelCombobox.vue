<script setup lang="ts">
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import AppCombobox from '@/components/forms/AppCombobox.vue';

type ModelOption = { id: string; label: string };

const {
  url,
  hasError = false,
  disabled = false,
  id,
} = defineProps<{
  /** JSON endpoint that takes `?search=` and returns `{ id, label }[]`. */
  url: string;
  hasError?: boolean;
  disabled?: boolean;
  id?: string;
}>();

const model = defineModel<string | null>();
// ponytail: pass v-model:object to preselect; PTC re-searched by id on mount, which needs id lookup on every endpoint
const object = defineModel<ModelOption | null>('object', { default: null });

const selected = computed({
  get: () => object.value,
  set: (value: ModelOption | null) => {
    object.value = value;
    model.value = value?.id ?? null;
  },
});

const query = ref('');
const options = ref<ModelOption[]>([]);
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
    if (term.length > 2) {
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
  />
</template>
