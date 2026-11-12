<script setup lang="ts">
import { Clock } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { trans } from 'laravel-vue-i18n';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import FieldError from '@/components/forms/FieldError.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import FormField from '@/components/forms/FormField.vue';
import { InputField, SelectField } from '@/components/forms/fields';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Modal from '@/components/modals/Modal.vue';
import SettingsNav from '@/components/SettingsNav.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { guessTimezone } from '@/lib/dates';
import { destroy, store, update } from '@/routes/business-hours';

defineOptions({ layout: { title: trans('Business hours') } });

type Schedule = App.Data.BusinessSchedules.BusinessScheduleData;

const { schedules, timezones, canManage } = defineProps<{
  schedules: Schedule[];
  timezones: string[];
  canManage: boolean;
}>();

const days = computed(() => ({
  mon: trans('Monday'),
  tue: trans('Tuesday'),
  wed: trans('Wednesday'),
  thu: trans('Thursday'),
  fri: trans('Friday'),
  sat: trans('Saturday'),
  sun: trans('Sunday'),
}));

/** The schedule being edited, `'new'` while creating one, or null when the modal is closed. */
const editing = ref<Schedule | 'new' | null>(null);
const deleting = ref<Schedule | null>(null);
const deleteLoading = ref(false);

const form = useForm({
  name: '',
  timezone: '',
  weekly_hours: {} as Record<string, string[][]>,
  holidays: [] as string[],
});

/**
 * `<input type="time">` can't express 24:00, so an end of midnight is shown as 00:00:
 * "24:00" becomes "00:00" when loading and back again when submitting.
 */
const toInput = (hours: Record<string, string[][]>) =>
  Object.fromEntries(
    Object.keys(days.value).map((day) => [
      day,
      (hours[day] ?? []).map(([start, end]) => [
        start,
        end === '24:00' ? '00:00' : end,
      ]),
    ]),
  );

const toPayload = (hours: Record<string, string[][]>) =>
  Object.fromEntries(
    Object.entries(hours)
      .filter(([, intervals]) => intervals.length)
      .map(([day, intervals]) => [
        day,
        intervals.map(([start, end]) => [
          start,
          end === '00:00' && start !== '' ? '24:00' : end,
        ]),
      ]),
  );

const summary = (schedule: Schedule): string =>
  Object.entries(days.value)
    .filter(([day]) => schedule.weekly_hours[day]?.length)
    .map(
      ([day, label]) =>
        `${label.slice(0, 3)} ${schedule.weekly_hours[day].map(([s, e]) => `${s}–${e}`).join(', ')}`,
    )
    .join(' · ');

/** Errors come back as `weekly_hours.mon.1` (an interval), `weekly_hours.mon` (overlap) or `weekly_hours`. */
const errorFor = (prefix: string, exact = false): string | undefined => {
  const errors: Record<string, string> = form.errors;

  return (
    errors[prefix] ??
    (exact
      ? undefined
      : Object.entries(errors).find(([name]) =>
          name.startsWith(`${prefix}.`),
        )?.[1])
  );
};

function open(schedule: Schedule | 'new'): void {
  form.clearErrors();
  form.defaults({
    name: schedule === 'new' ? '' : schedule.name,
    timezone: schedule === 'new' ? guessTimezone() : schedule.timezone,
    weekly_hours: toInput(
      schedule === 'new'
        ? Object.fromEntries(
            ['mon', 'tue', 'wed', 'thu', 'fri'].map((day) => [
              day,
              [['09:00', '17:00']],
            ]),
          )
        : schedule.weekly_hours,
    ),
    holidays: schedule === 'new' ? [] : [...schedule.holidays],
  });
  form.reset();
  editing.value = schedule;
}

function save(close: () => void): void {
  const schedule = editing.value;

  form
    .transform((data) => ({
      ...data,
      weekly_hours: toPayload(data.weekly_hours),
      holidays: data.holidays.filter(Boolean),
    }))
    .submit(
      schedule === 'new' || schedule === null ? store() : update(schedule.id),
      { preserveScroll: true, onSuccess: close },
    );
}

function remove(close: () => void): void {
  router.delete(destroy(deleting.value!.id).url, {
    preserveScroll: true,
    onStart: () => (deleteLoading.value = true),
    onFinish: () => (deleteLoading.value = false),
    onSuccess: close,
  });
}
</script>

<template>
  <Head :title="$t('Business hours')" />

  <SettingsNav />

  <div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{
          $t(
            'Weekly working hours and holidays, used to measure SLAs in business time.',
          )
        }}
      </p>
      <AppButton
        v-if="canManage"
        type="button"
        class="shrink-0"
        @click="open('new')"
        >{{ $t('New schedule') }}</AppButton
      >
    </div>

    <Table v-if="schedules.length">
      <Thead>
        <tr>
          <Th>{{ $t('Name') }}</Th>
          <Th>{{ $t('Timezone') }}</Th>
          <Th>{{ $t('Hours') }}</Th>
          <Th right
            ><span class="sr-only">{{ $t('Actions') }}</span></Th
          >
        </tr>
      </Thead>
      <Tbody>
        <tr v-for="schedule in schedules" :key="schedule.id">
          <Td darker>{{ schedule.name }}</Td>
          <Td>{{ schedule.timezone }}</Td>
          <Td>{{ summary(schedule) }}</Td>
          <Td class="space-x-2 text-end whitespace-nowrap">
            <template v-if="canManage">
              <AppButton
                type="button"
                size="xs"
                color="white"
                @click="open(schedule)"
                >{{ $t('Edit') }}</AppButton
              >
              <AppButton
                type="button"
                size="xs"
                color="red"
                @click="deleting = schedule"
                >{{ $t('Delete') }}</AppButton
              >
            </template>
          </Td>
        </tr>
      </Tbody>
    </Table>
    <EmptyState
      v-else
      :icon="Clock"
      :title="$t('There are no business hours yet.')"
    />
  </div>

  <Modal
    v-if="editing"
    :headline="editing === 'new' ? $t('New schedule') : $t('Edit schedule')"
    :action-loading="form.processing"
    @close="editing = null"
    @action="save"
  >
    <form class="mt-4" @submit.prevent>
      <AppFieldset>
        <InputField
          v-model="form.name"
          :label="$t('Name')"
          :error="form.errors.name"
          required
        />
        <SelectField
          v-model="form.timezone"
          :label="$t('Timezone')"
          :options="timezones"
          :error="form.errors.timezone"
          required
        />

        <div class="space-y-3">
          <p class="text-sm font-medium text-gray-900 dark:text-white">
            {{ $t('Weekly hours') }}
          </p>
          <FieldError v-if="errorFor('weekly_hours', true)">
            {{ errorFor('weekly_hours', true) }}
          </FieldError>
          <div
            v-for="(label, day) in days"
            :key="day"
            class="space-y-1 border-b border-gray-200 pb-3 last:border-0 dark:border-gray-700"
          >
            <div class="flex items-start gap-3">
              <span class="w-24 pt-2 text-sm text-gray-700 dark:text-gray-300">
                {{ label }}
              </span>
              <div class="flex-1 space-y-2">
                <p
                  v-if="!form.weekly_hours[day]?.length"
                  class="pt-2 text-sm text-gray-400"
                >
                  {{ $t('Closed') }}
                </p>
                <div
                  v-for="(interval, index) in form.weekly_hours[day]"
                  :key="index"
                  class="space-y-1"
                >
                  <div class="flex items-center gap-2">
                    <FormField
                      v-model="interval[0]"
                      type="time"
                      :error="errorFor(`weekly_hours.${day}.${index}`)"
                    />
                    <span class="text-gray-500">{{ $t('to') }}</span>
                    <FormField
                      v-model="interval[1]"
                      type="time"
                      :error="errorFor(`weekly_hours.${day}.${index}`)"
                    />
                    <AppButton
                      type="button"
                      size="xs"
                      color="white"
                      :aria-label="$t('Remove :day interval', { day: label })"
                      @click="form.weekly_hours[day].splice(index, 1)"
                      >{{ $t('Remove') }}</AppButton
                    >
                  </div>
                </div>
                <FieldError v-if="errorFor(`weekly_hours.${day}`, true)">
                  {{ errorFor(`weekly_hours.${day}`, true) }}
                </FieldError>
              </div>
              <AppButton
                type="button"
                size="xs"
                color="white"
                @click="
                  (form.weekly_hours[day] ??= []).push(['09:00', '17:00'])
                "
                >{{ $t('Add') }}</AppButton
              >
            </div>
          </div>
          <p class="text-xs text-gray-500">
            {{ $t('An end of 00:00 means midnight, the end of that day.') }}
          </p>
        </div>

        <div class="space-y-2">
          <p class="text-sm font-medium text-gray-900 dark:text-white">
            {{ $t('Holidays') }}
          </p>
          <div
            v-for="(_, index) in form.holidays"
            :key="index"
            class="flex items-center gap-2"
          >
            <FormField
              v-model="form.holidays[index]"
              type="date"
              :error="errorFor(`holidays.${index}`)"
            />
            <AppButton
              type="button"
              size="xs"
              color="white"
              @click="form.holidays.splice(index, 1)"
              >{{ $t('Remove') }}</AppButton
            >
          </div>
          <FieldError v-if="errorFor('holidays', true)">
            {{ errorFor('holidays', true) }}
          </FieldError>
          <AppButton
            type="button"
            size="xs"
            color="white"
            @click="form.holidays.push('')"
            >{{ $t('Add holiday') }}</AppButton
          >
        </div>
      </AppFieldset>
    </form>
  </Modal>

  <ConfirmationModal
    v-if="deleting"
    :loading="deleteLoading"
    @close="deleting = null"
    @confirmed="remove"
  >
    <template #headline>{{
      $t('Delete “:name”?', { name: deleting.name })
    }}</template
    >{{ $t('Anything measured against this schedule can no longer use it.')
    }}<template #actionText>{{ $t('Delete schedule') }}</template>
  </ConfirmationModal>
</template>
