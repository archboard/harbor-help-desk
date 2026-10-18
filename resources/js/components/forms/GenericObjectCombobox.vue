<script
  setup
  lang="ts"
  generic="T extends Record<string, unknown> = Record<string, unknown>"
>
import { computed, ref } from 'vue';
import AppCombobox from '@/components/forms/AppCombobox.vue';

const {
  options,
  hasError = false,
  disabled = false,
  id,
  searchAttribute = 'name',
} = defineProps<{
  options: T[];
  hasError?: boolean;
  disabled?: boolean;
  id?: string;
  searchAttribute?: string;
}>();

const model = defineModel<T | null>({ default: null });
const objectId = defineModel<string | number | null>('objectId');

const selected = computed({
  get: () => model.value,
  set: (value: T | null) => {
    model.value = value;
    objectId.value = (value?.id as string | number | undefined) ?? null;
  },
});

const query = ref('');
const filteredOptions = computed(() => {
  const search = query.value.toLowerCase();

  return options
    .filter((option) =>
      String(option[searchAttribute] ?? '')
        .toLowerCase()
        .includes(search),
    )
    .slice(0, 10);
});
</script>

<template>
  <AppCombobox
    :id="id"
    v-model="selected"
    v-model:query="query"
    :options="filteredOptions"
    :has-error="hasError"
    :disabled="disabled"
  />
</template>
