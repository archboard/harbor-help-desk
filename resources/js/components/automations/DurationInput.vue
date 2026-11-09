<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
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
 * (negative is in the past) and a direction choice sets its sign: "ago / from now", or
 * `directions` ("before / after").
 */
const {
  schedules = [],
  relative = false,
  directions = [trans('ago'), trans('from now')],
  errors = {},
} = defineProps<{
  schedules?: App.Data.BusinessSchedules.BusinessScheduleData[];
  relative?: boolean;
  /** The labels of the past and future choices. */
  directions?: [past: string, future: string];
  /** Server errors keyed `amount`, `unit` and `business_schedule_id`. */
  errors?: Record<string, string>;
}>();

const model = defineModel<Interval>({
  default: () => ({ amount: 1, unit: 'days' }),
});

const units = computed(() => [
  { value: 'minutes', label: trans('Minutes') },
  { value: 'hours', label: trans('Hours') },
  { value: 'days', label: trans('Days') },
]);

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
          :aria-label="$t('Amount')"
          :has-error="!!errors.amount"
          :model-value="size"
          @update:model-value="setAmount"
        />
      </div>
      <div class="w-32">
        <AppSelect
          hide-null
          :aria-label="$t('Unit')"
          :has-error="!!errors.unit"
          :options="units"
          :model-value="model.unit"
          @update:model-value="update({ unit: String($event) })"
        />
      </div>
      <div v-if="relative" class="w-32">
        <AppSelect
          hide-null
          :aria-label="$t('Direction')"
          :options="[
            { value: 'ago', label: directions[0] },
            { value: 'from_now', label: directions[1] },
          ]"
          :model-value="past ? 'ago' : 'from_now'"
          @update:model-value="setDirection"
        />
      </div>
      <div v-if="schedules.length" class="w-48">
        <AppSelect
          :null-option="$t('Calendar time')"
          clearable
          :aria-label="$t('Business schedule')"
          :has-error="!!errors.business_schedule_id"
          :options="
            schedules.map((schedule) => ({
              value: schedule.id,
              label: $t('Business hours: :name', {
                name: schedule.name,
              }),
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
