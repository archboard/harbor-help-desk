<script setup lang="ts">
import { computed } from 'vue';
import AppLabel from '@/components/forms/AppLabel.vue';
import FieldError from '@/components/forms/FieldError.vue';
import { SelectField } from '@/components/forms/fields';
import AssigneePicker from '@/components/tickets/AssigneePicker.vue';
import { fieldFor } from '../summaries';
import { optionsOf } from './types';
import type { ActionEditorProps } from './types';

/**
 * The "Assign" action: add, replace or clear the assignees, or let a desk's rotation pick one.
 * A desk-scoped automation can only use its own desk's rotation.
 */
const {
  catalog,
  desks,
  scopeDeskId,
  errors = {},
} = defineProps<ActionEditorProps>();

const config = defineModel<{
  mode?: string;
  user_ids?: string[] | null;
  desk_id?: string | null;
}>({ required: true });

const members = computed(() =>
  (fieldFor(catalog, 'ticket.assignees')?.options ?? []).map((option) => ({
    id: option.value,
    name: option.label,
  })),
);

const usesUsers = computed(() =>
  ['add', 'replace'].includes(config.value.mode ?? ''),
);
const usesRotation = computed(() =>
  ['round_robin', 'load_balanced'].includes(config.value.mode ?? ''),
);

const userError = computed(
  () =>
    errors.user_ids ??
    Object.entries(errors).find(([key]) => key.startsWith('user_ids.'))?.[1],
);

function setMode(mode: unknown): void {
  config.value = {
    mode: String(mode),
    ...(['add', 'replace'].includes(String(mode)) ? { user_ids: [] } : {}),
    ...(['round_robin', 'load_balanced'].includes(String(mode))
      ? { desk_id: scopeDeskId }
      : {}),
  };
}
</script>

<template>
  <div class="space-y-4">
    <SelectField
      :model-value="config.mode ?? null"
      label="Mode"
      required
      :options="optionsOf(catalog, 'assign', 'mode')"
      :error="errors.mode"
      @update:model-value="setMode"
    />
    <div v-if="usesUsers" class="space-y-1">
      <AppLabel :has-error="!!userError">People</AppLabel>
      <AssigneePicker
        :model-value="config.user_ids ?? []"
        :members="members"
        @update:model-value="config.user_ids = $event"
      />
      <FieldError v-if="userError">{{ userError }}</FieldError>
    </div>
    <SelectField
      v-if="usesRotation"
      :model-value="config.desk_id ?? null"
      label="Desk"
      required
      :options="
        desks
          .filter((desk) => !scopeDeskId || desk.id === scopeDeskId)
          .map((desk) => ({ value: desk.id, label: desk.name }))
      "
      :error="errors.desk_id"
      @update:model-value="config.desk_id = $event as string | null"
    />
  </div>
</template>
