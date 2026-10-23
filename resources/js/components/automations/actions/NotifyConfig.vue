<script setup lang="ts">
import { computed } from 'vue';
import AppLabel from '@/components/forms/AppLabel.vue';
import FieldError from '@/components/forms/FieldError.vue';
import { InputField, SelectField } from '@/components/forms/fields';
import AssigneePicker from '@/components/tickets/AssigneePicker.vue';
import PlaceholderTextarea from '../PlaceholderTextarea.vue';
import { fieldFor } from '../summaries';
import { optionsOf } from './types';
import type { ActionEditorProps } from './types';

/** The "Send a notification" action: who gets it, and the subject and message with placeholders. */
const { catalog, trigger, errors = {} } = defineProps<ActionEditorProps>();

const config = defineModel<{
  recipients?: string;
  user_ids?: string[] | null;
  subject?: string;
  body?: string;
}>({ required: true });

const members = computed(() =>
  (fieldFor(catalog, 'ticket.assignees')?.options ?? []).map((option) => ({
    id: option.value,
    name: option.label,
  })),
);

const userError = computed(
  () =>
    errors.user_ids ??
    Object.entries(errors).find(([key]) => key.startsWith('user_ids.'))?.[1],
);

function setRecipients(recipients: unknown): void {
  const { user_ids: _ignored, ...rest } = config.value;

  config.value = {
    ...rest,
    recipients: String(recipients),
    ...(recipients === 'users' ? { user_ids: [] } : {}),
  };
}
</script>

<template>
  <div class="space-y-4">
    <SelectField
      :model-value="config.recipients ?? null"
      label="Notify"
      required
      :options="optionsOf(catalog, 'notify', 'recipients')"
      :error="errors.recipients"
      @update:model-value="setRecipients"
    />
    <div v-if="config.recipients === 'users'" class="space-y-1">
      <AppLabel :has-error="!!userError">People</AppLabel>
      <AssigneePicker
        :model-value="config.user_ids ?? []"
        :members="members"
        @update:model-value="config.user_ids = $event"
      />
      <FieldError v-if="userError">{{ userError }}</FieldError>
    </div>
    <InputField
      v-model="config.subject"
      label="Subject"
      required
      :error="errors.subject"
    />
    <PlaceholderTextarea
      v-model="config.body"
      :catalog="catalog"
      :trigger="trigger"
      label="Message"
      required
      :error="errors.body"
    />
  </div>
</template>
