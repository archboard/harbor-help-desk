<script setup lang="ts">
import { computed, ref } from 'vue';
import AppInput from '@/components/forms/AppInput.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import FieldError from '@/components/forms/FieldError.vue';

export type Interval = {
  amount: number;
  unit: string;
  business_schedule_id?: string | null;
};

/**
 * An amount, a unit and an optional business schedule. With `relative` the amount is signed
 * (negative is in the past) and a "ago / from now" choice sets its sign.
 */
const {
  schedules = [],
  relative = false,
  errors = {},
} = defineProps<{
  schedules?: App.Data.BusinessSchedules.BusinessScheduleData[];
  relative?: boolean;
  /** Server errors keyed `amount`, `unit` and `business_schedule_id`. */
  errors?: Record<string, string>;
}>();

const model = defineModel<Interval>({
  default: () => ({ amount: 1, unit: 'days' }),
});

const units = [
  { value: 'minutes', label: 'Minutes' },
  { value: 'hours', label: 'Hours' },
  { value: 'days', label: 'Days' },
];

const past = ref(model.value.amount <= 0);
const size = computed(() => Math.abs(model.value.amount));

function update(patch: Partial<Interval>): void {
  model.value = { ...model.value, ...patch };
}

function setAmount(value: string | number | null | undefined): void {
  const amount = Math.abs(Math.trunc(Number(value) || 0));

  update({ amount: relative && past.value ? -amount : amount });
}

function setDirection(
  value: string | number | boolean | null | undefined,
): void {
  past.value = value === 'ago';
  update({ amount: past.value ? -size.value : size.value });
}

const message = computed(
  () => errors.amount ?? errors.unit ?? errors.business_schedule_id,
);
</script>

<template>
  <div class="space-y-1">
    <div class="flex flex-wrap items-center gap-2">
      <div class="w-24">
        <AppInput
          type="number"
          min="0"
          aria-label="Amount"
          :has-error="!!errors.amount"
          :model-value="size"
          @update:model-value="setAmount"
        />
      </div>
      <div class="w-32">
        <AppSelect
          hide-null
          aria-label="Unit"
          :has-error="!!errors.unit"
          :options="units"
          :model-value="model.unit"
          @update:model-value="update({ unit: String($event) })"
        />
      </div>
      <div v-if="relative" class="w-32">
        <AppSelect
          hide-null
          aria-label="Direction"
          :options="[
            { value: 'ago', label: 'ago' },
            { value: 'from_now', label: 'from now' },
          ]"
          :model-value="past ? 'ago' : 'from_now'"
          @update:model-value="setDirection"
        />
      </div>
      <div v-if="schedules.length" class="w-48">
        <AppSelect
          null-option="Calendar time"
          clearable
          aria-label="Business schedule"
          :has-error="!!errors.business_schedule_id"
          :options="
            schedules.map((schedule) => ({
              value: schedule.id,
              label: `Business hours: ${schedule.name}`,
            }))
          "
          :model-value="model.business_schedule_id ?? null"
          @update:model-value="
            update({
              business_schedule_id: $event === null ? null : String($event),
            })
          "
        />
      </div>
    </div>
    <FieldError v-if="message">{{ message }}</FieldError>
  </div>
</template>
