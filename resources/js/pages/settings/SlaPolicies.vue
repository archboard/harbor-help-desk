<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
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
import { destroy, store, update } from '@/routes/sla-policies';

defineOptions({ layout: { title: trans('SLA policies') } });

type Policy = App.Data.SlaPolicies.SlaPolicyData;
type Cells = Record<string, Record<string, { hours: string; minutes: string }>>;

const { policies, schedules, priorities, metrics, canManage } = defineProps<{
  policies: Policy[];
  schedules: App.Data.BusinessSchedules.BusinessScheduleData[];
  priorities: Record<App.Enums.TicketPriority, string>;
  metrics: Record<App.Enums.SlaMetric, string>;
  canManage: boolean;
}>();

/** The policy being edited, `'new'` while creating one, or null when the modal is closed. */
const editing = ref<Policy | 'new' | null>(null);
const deleting = ref<Policy | null>(null);
const deleteLoading = ref(false);

const scheduleOptions = {
  '': '24/7',
  ...Object.fromEntries(schedules.map(({ id, name }) => [id, name])),
};

const form = useForm({
  name: '',
  business_schedule_id: '',
  warning_percent: '75',
  targets: {} as Cells,
});

/** Targets are stored as minutes; the grid edits hours and minutes. Empty cells aren't tracked. */
const toCells = (targets: Policy['targets']): Cells =>
  Object.fromEntries(
    Object.keys(priorities).map((priority) => [
      priority,
      Object.fromEntries(
        Object.keys(metrics).map((metric) => {
          const total = targets[priority]?.[metric];

          return [
            metric,
            total === undefined
              ? { hours: '', minutes: '' }
              : {
                  hours: String(Math.floor(total / 60)),
                  minutes: String(total % 60),
                },
          ];
        }),
      ),
    ]),
  );

const toTargets = (cells: Cells): Policy['targets'] => {
  const targets: Policy['targets'] = {};

  for (const [priority, row] of Object.entries(cells)) {
    for (const [metric, { hours, minutes }] of Object.entries(row)) {
      if (hours === '' && minutes === '') {
        continue;
      }

      (targets[priority] ??= {})[metric] =
        Number(hours || 0) * 60 + Number(minutes || 0);
    }
  }

  return targets;
};

const format = (total: number): string => {
  const [hours, minutes] = [Math.floor(total / 60), total % 60];

  return [hours ? `${hours}h` : '', minutes ? `${minutes}m` : '']
    .filter(Boolean)
    .join(' ');
};

const summary = (policy: Policy): string =>
  Object.entries(priorities)
    .filter(([priority]) => policy.targets[priority])
    .map(
      ([priority, label]) =>
        `${label}: ${Object.entries(metrics)
          .filter(([metric]) => policy.targets[priority][metric])
          .map(
            ([metric, name]) =>
              `${name} ${format(policy.targets[priority][metric])}`,
          )
          .join(', ')}`,
    )
    .join(' · ');

function open(policy: Policy | 'new'): void {
  form.clearErrors();
  form.defaults({
    name: policy === 'new' ? '' : policy.name,
    business_schedule_id:
      policy === 'new' ? '' : (policy.business_schedule_id ?? ''),
    warning_percent: policy === 'new' ? '75' : String(policy.warning_percent),
    targets: toCells(policy === 'new' ? {} : policy.targets),
  });
  form.reset();
  editing.value = policy;
}

function save(close: () => void): void {
  const policy = editing.value;

  form
    .transform((data) => ({
      ...data,
      business_schedule_id: data.business_schedule_id || null,
      targets: toTargets(data.targets),
    }))
    .submit(policy === 'new' || policy === null ? store() : update(policy.id), {
      preserveScroll: true,
      onSuccess: close,
    });
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
  <Head :title="$t('SLA policies')" />

  <SettingsNav />

  <div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{
          $t(
            'How quickly tickets must get a first response and a resolution, per priority. Assign a policy to each desk.',
          )
        }}
      </p>
      <AppButton
        v-if="canManage"
        type="button"
        class="shrink-0"
        @click="open('new')"
        >{{ $t('New policy') }}</AppButton
      >
    </div>

    <Table v-if="policies.length">
      <Thead>
        <tr>
          <Th>{{ $t('Name') }}</Th>
          <Th>{{ $t('Schedule') }}</Th>
          <Th>{{ $t('Warning') }}</Th>
          <Th>{{ $t('Targets') }}</Th>
          <Th right
            ><span class="sr-only">{{ $t('Actions') }}</span></Th
          >
        </tr>
      </Thead>
      <Tbody>
        <tr v-for="policy in policies" :key="policy.id">
          <Td darker>{{ policy.name }}</Td>
          <Td>{{ policy.business_schedule?.name ?? '24/7' }}</Td>
          <Td>{{ policy.warning_percent }}%</Td>
          <Td>{{ summary(policy) || $t('Nothing tracked') }}</Td>
          <Td class="space-x-2 text-end whitespace-nowrap">
            <template v-if="canManage">
              <AppButton
                type="button"
                size="xs"
                color="white"
                @click="open(policy)"
                >{{ $t('Edit') }}</AppButton
              >
              <AppButton
                type="button"
                size="xs"
                color="red"
                @click="deleting = policy"
                >{{ $t('Delete') }}</AppButton
              >
            </template>
          </Td>
        </tr>
      </Tbody>
    </Table>
    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
      {{ $t('There are no SLA policies yet.') }}
    </p>
  </div>

  <Modal
    v-if="editing"
    :headline="editing === 'new' ? $t('New policy') : $t('Edit policy')"
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
          v-model="form.business_schedule_id"
          :label="$t('Schedule')"
          :options="scheduleOptions"
          :error="form.errors.business_schedule_id"
        />
        <InputField
          v-model="form.warning_percent"
          type="number"
          :label="$t('Warning at (% of target elapsed)')"
          :error="form.errors.warning_percent"
          required
        />

        <div class="space-y-3">
          <p class="text-sm font-medium text-gray-900 dark:text-white">
            {{ $t('Targets') }}
          </p>
          <FieldError v-if="form.errors.targets">
            {{ form.errors.targets }}
          </FieldError>
          <div
            v-for="(priorityLabel, priority) in priorities"
            :key="priority"
            class="space-y-2 border-b border-gray-200 pb-3 last:border-0 dark:border-gray-700"
          >
            <p class="text-sm text-gray-700 dark:text-gray-300">
              {{ priorityLabel }}
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
              <div
                v-for="(metricLabel, metric) in metrics"
                :key="metric"
                class="space-y-1"
              >
                <p class="text-xs text-gray-500">{{ metricLabel }}</p>
                <div class="flex items-center gap-2">
                  <FormField
                    v-model="form.targets[priority][metric].hours"
                    type="number"
                    :min="0"
                    :error="
                      (form.errors as Record<string, string>)[
                        `targets.${priority}.${metric}`
                      ]
                    "
                  />
                  <span class="text-sm text-gray-500">{{ $t('h') }}</span>
                  <FormField
                    v-model="form.targets[priority][metric].minutes"
                    type="number"
                    :min="0"
                    :max="59"
                  />
                  <span class="text-sm text-gray-500">{{ $t('m') }}</span>
                </div>
              </div>
            </div>
          </div>
          <p class="text-xs text-gray-500">
            {{ $t('Leave a target empty to not track it.') }}
          </p>
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
    >{{ $t('Desks using this policy will no longer have an SLA.')
    }}<template #actionText>{{ $t('Delete policy') }}</template>
  </ConfirmationModal>
</template>
