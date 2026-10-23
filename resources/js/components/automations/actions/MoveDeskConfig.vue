<script setup lang="ts">
import { SelectField } from '@/components/forms/fields';
import type { ActionEditorProps } from './types';

/** The "Move to a desk" action. The desks offered are the ones the editor may open tickets on. */
const { moveDesks, errors = {} } = defineProps<ActionEditorProps>();

const config = defineModel<{ desk_id?: string | null }>({ required: true });
</script>

<template>
  <SelectField
    v-model="config.desk_id"
    label="Desk"
    required
    :options="moveDesks.map((desk) => ({ value: desk.id, label: desk.name }))"
    :error="errors.desk_id"
  />
</template>
