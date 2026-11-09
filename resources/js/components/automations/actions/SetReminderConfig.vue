<script setup lang="ts">
import AppLabel from '@/components/forms/AppLabel.vue';
import DurationInput from '../DurationInput.vue';
import type { Interval } from '../DurationInput.vue';
import { errorsUnder } from '../errors';
import PlaceholderTextarea from '../PlaceholderTextarea.vue';
import type { ActionEditorProps } from './types';

/** The "Set a reminder" action: due after a while, in calendar or business time, with an optional note. */
const {
  catalog,
  trigger,
  schedules,
  errors = {},
} = defineProps<ActionEditorProps>();

const config = defineModel<{ after?: Interval; note?: string | null }>({
  required: true,
});

if (!config.value.after) {
  config.value = { ...config.value, after: { amount: 1, unit: 'hours' } };
}
</script>

<template>
  <div class="space-y-4">
    <div class="space-y-1">
      <AppLabel :has-error="!!errors.after">{{ $t('Remind after') }}</AppLabel>
      <DurationInput
        v-model="config.after as Interval"
        :schedules="schedules"
        :errors="{
          ...errorsUnder(errors, 'after.'),
          amount: errors.after ?? errorsUnder(errors, 'after.').amount,
        }"
      />
    </div>
    <PlaceholderTextarea
      v-model="config.note"
      :catalog="catalog"
      :trigger="trigger"
      :label="$t('Note')"
      :error="errors.note"
    />
  </div>
</template>
