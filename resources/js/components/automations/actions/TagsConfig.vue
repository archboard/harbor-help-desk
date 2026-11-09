<script setup lang="ts">
import { SelectField } from '@/components/forms/fields';
import TagsInput from '@/components/forms/TagsInput.vue';
import { fieldFor } from '../summaries';
import { optionsOf } from './types';
import type { ActionEditorProps } from './types';

/** The "Change tags" action: add, remove or replace, with the workspace's tags as suggestions. */
const { catalog, errors = {} } = defineProps<ActionEditorProps>();

const config = defineModel<{ mode?: string; names?: string[] }>({
  required: true,
});
</script>

<template>
  <div class="space-y-4">
    <SelectField
      v-model="config.mode"
      :label="$t('Mode')"
      required
      :options="optionsOf(catalog, 'tags', 'mode')"
      :error="errors.mode"
    />
    <TagsInput
      v-model="config.names"
      :label="$t('Tags')"
      :options="fieldFor(catalog, 'ticket.tags')?.options"
      :error="
        errors.names ??
        Object.entries(errors).find(([key]) => key.startsWith('names.'))?.[1]
      "
    />
  </div>
</template>
