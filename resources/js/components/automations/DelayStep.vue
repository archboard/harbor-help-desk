<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLabel from '@/components/forms/AppLabel.vue';
import FieldError from '@/components/forms/FieldError.vue';
import { CheckboxListField, SelectField } from '@/components/forms/fields';
import DurationInput from './DurationInput.vue';
import type { Interval } from './DurationInput.vue';
import { errorsUnder } from './errors';
import FieldPicker from './FieldPicker.vue';

type Delay = App.Data.Automations.DelayStepData;

/**
 * A delay: wait for a duration (in calendar or business time) or until a ticket date, and
 * optionally give up when one of the chosen triggers happens first.
 */
const {
  catalog,
  groups,
  schedules,
  errors = {},
} = defineProps<{
  catalog: App.Data.Automations.AutomationCatalogData;
  groups: App.Automations.FieldGroup[];
  schedules: App.Data.BusinessSchedules.BusinessScheduleData[];
  /** Server errors relative to the step: `for.amount`, `until.field`, `cancel_on`. */
  errors?: Record<string, string>;
}>();

const step = defineModel<Delay>({ required: true });

const MINUTES = { minutes: 1, hours: 60, days: 1440 };

/** A signed number of minutes as the largest unit that divides it evenly. */
function toInterval(offset: number): Interval {
  const unit =
    offset % 1440 === 0 ? 'days' : offset % 60 === 0 ? 'hours' : 'minutes';

  return { amount: offset / MINUTES[unit], unit };
}

// Held here, since turning it back into minutes each time would change the unit being edited.
const offset = ref<Interval>(toInterval(step.value.until?.offset ?? 0));

const mode = computed(() => (step.value.until ? 'until' : 'for'));

function setMode(value: string | number | boolean | null | undefined): void {
  if (value === mode.value) {
    return;
  }

  if (value === 'until') {
    offset.value = toInterval(0);
    step.value.for = null;
    step.value.until = { field: '', offset: 0 };
  } else {
    step.value.until = null;
    step.value.for = { amount: 1, unit: 'hours', business_schedule_id: null };
  }
}

function setOffset(interval: Interval): void {
  offset.value = interval;
  step.value.until!.offset =
    interval.amount * MINUTES[interval.unit as keyof typeof MINUTES];
}

// ponytail: hardcoded; these never match an event on a waiting ticket. A catalog flag is the upgrade path.
const NEVER_CANCELS = ['ticket.created', 'schedule', 'webhook'];

/** Triggers that can cancel a wait, plus any excluded one already chosen so it can be unchecked. */
const triggerOptions = computed(() =>
  catalog.triggers
    .filter(
      (trigger) =>
        !NEVER_CANCELS.includes(trigger.key) ||
        step.value.cancel_on.includes(trigger.key),
    )
    .map((trigger) => ({ value: trigger.key, label: trigger.label })),
);
</script>

<template>
  <div class="space-y-4">
    <SelectField
      :model-value="mode"
      :label="$t('Wait')"
      :options="[
        { value: 'for', label: $t('For a duration') },
        { value: 'until', label: $t('Until a ticket date') },
      ]"
      @update:model-value="setMode"
    />

    <div v-if="step.for" class="space-y-1">
      <AppLabel :has-error="!!errors.for">{{ $t('Duration') }}</AppLabel>
      <DurationInput
        v-model="step.for as Interval"
        :schedules="schedules"
        :errors="errorsUnder(errors, 'for.')"
      />
      <FieldError v-if="errors.for">{{ errors.for }}</FieldError>
    </div>

    <div v-else-if="step.until" class="space-y-4">
      <div class="space-y-1">
        <AppLabel :has-error="!!errors['until.offset']">
          {{ $t('Time before or after') }}
        </AppLabel>
        <DurationInput
          :model-value="offset"
          relative
          :directions="[$t('before'), $t('after')]"
          :errors="{ amount: errors['until.offset'] }"
          @update:model-value="setOffset"
        />
      </div>
      <div class="space-y-1">
        <AppLabel :has-error="!!errors['until.field']">{{
          $t('Ticket date')
        }}</AppLabel>
        <FieldPicker
          v-model="step.until.field"
          :catalog="catalog"
          :groups="groups"
          type="datetime"
          :has-error="!!errors['until.field']"
        />
        <FieldError v-if="errors['until.field']">
          {{ errors['until.field'] }}
        </FieldError>
        <FieldError v-if="errors.until">{{ errors.until }}</FieldError>
      </div>
    </div>

    <CheckboxListField
      v-model="step.cancel_on as (string | number | boolean)[]"
      :label="$t('Cancel if…')"
      :help-text="
        $t(
          'Ends the wait, and the rest of the automation, when one of these happens first.',
        )
      "
      :options="triggerOptions"
      :error="errors.cancel_on"
    />
  </div>
</template>
