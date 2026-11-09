<script setup lang="ts">
import { computed } from 'vue';
import { SelectField } from '@/components/forms/fields';
import type { ActionEditorProps } from './types';

/**
 * The "Apply a macro" action. Only shared macros can be used, and a desk-scoped automation only
 * the ones for its desk or for every desk.
 */
const { macros, scopeDeskId, errors = {} } = defineProps<ActionEditorProps>();

const config = defineModel<{ macro_id?: string | null }>({ required: true });

const options = computed(() =>
  macros
    .filter(
      (macro) =>
        !scopeDeskId || !macro.desk_id || macro.desk_id === scopeDeskId,
    )
    .map((macro) => ({ value: macro.id, label: macro.name })),
);
</script>

<template>
  <SelectField
    v-model="config.macro_id"
    :label="$t('Macro')"
    required
    :options="options"
    :error="errors.macro_id"
  />
</template>
