<script setup lang="ts">
import { Deferred, Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, provide, ref, watchEffect } from 'vue';
import { builderKey } from '@/components/automations/builder';
import { availableGroups } from '@/components/automations/conditions';
import { runDuration, statusColors } from '@/components/automations/runStatus';
import { TRIGGER_CARD } from '@/components/automations/errors';
import StepList from '@/components/automations/StepList.vue';
import { fieldFor } from '@/components/automations/summaries';
import type { LogEntry } from '@/components/automations/testResult';
import TriggerCard from '@/components/automations/TriggerCard.vue';
import AppButton from '@/components/AppButton.vue';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Pill from '@/components/Pill.vue';
import RelativeTime from '@/components/RelativeTime.vue';
import { useLiveReload } from '@/composables/useLiveReload';
import { formatDateTime } from '@/lib/dates';
import { cancel } from '@/routes/automation-runs';
import { index as history } from '@/routes/automations/runs';
import { show as showTicket } from '@/routes/tickets';

const { run, event, desks, schedules, macros, slaPolicies, catalog } =
  defineProps<{
    run: App.Data.Automations.AutomationRunData;
    /** The event that started the run, when the viewer may see its ticket. */
    event: App.Data.Tickets.TicketEventData | null;
    canCancel: boolean;
    statuses: Record<App.Enums.AutomationRunStatus, string>;
    desks: App.Data.Desks.DeskData[];
    schedules: App.Data.BusinessSchedules.BusinessScheduleData[];
    macros: App.Data.Macros.MacroData[];
    slaPolicies: App.Data.SlaPolicies.SlaPolicyData[];
    /** Deferred. */
    catalog?: App.Data.Automations.AutomationCatalogData;
  }>();

defineOptions({ layout: { title: '' } });

watchEffect(() =>
  setLayoutProps({ title: run.automation_name ?? trans('Run') }),
);

useLiveReload({
  channels: [`automations.${run.automation_id}`],
  event: '.AutomationRunChanged',
  only: ['run', 'event', 'canCancel'],
});

/** A copy, since the flow components take their steps as models. */
const definition = computed(() => structuredClone(run.definition!));

const entries = computed(() => run.log as LogEntry[]);

/** The log by step id. A webhook outcome shares the id of the step that sent it, so it is left out. */
const stepLog = computed(
  () =>
    new Map(
      entries.value
        .filter((entry) => entry.step_id && entry.type !== 'webhook')
        .map((entry) => [entry.step_id, entry]),
    ),
);

const webhooks = computed(() =>
  entries.value.filter((entry) => entry.type === 'webhook'),
);

/** Why the run was skipped or cancelled. */
const reasons = computed(() =>
  entries.value.filter((entry) => entry.reason && entry.type !== 'webhook'),
);

const openCard = ref<string | null>(null);

const names = computed<Record<string, string>>(() =>
  Object.fromEntries([
    ...desks.map((desk) => [desk.id, desk.name]),
    ...macros.map((macro) => [macro.id, macro.name]),
    ...slaPolicies.map((policy) => [policy.id, policy.name]),
    ...(catalog
      ? (fieldFor(catalog, 'ticket.assignees')?.options ?? [])
      : []
    ).map((user) => [user.value, user.label]),
  ]),
);

provide(builderKey, {
  catalog: computed(() => catalog!),
  trigger: computed(() => definition.value.trigger.type),
  groups: computed(() =>
    catalog ? availableGroups(catalog, definition.value.trigger.type) : [],
  ),
  names,
  desks: computed(() => desks),
  moveDesks: computed(() => desks),
  schedules: computed(() => schedules),
  macros: computed(() => macros),
  slaPolicies: computed(() => slaPolicies),
  scopeDeskId: computed(() => null),
  errors: computed(() => ({})),
  problems: computed(() => []),
  testLog: computed(() => stepLog.value),
  readonly: true,
  openCard,
});

const cancelling = ref(false);
const cancelLoading = ref(false);

function cancelRun(close: () => void): void {
  router.post(
    cancel(run.id).url,
    {},
    {
      preserveScroll: true,
      onStart: () => (cancelLoading.value = true),
      onFinish: () => (cancelLoading.value = false),
      onSuccess: close,
    },
  );
}
</script>

<template>
  <Head :title="run.automation_name ?? $t('Automation run')" />

  <div class="mx-auto max-w-3xl space-y-8">
    <div class="flex flex-wrap items-center gap-3">
      <Link
        :href="history(run.automation_id).url"
        class="text-sm text-gray-500 hover:underline dark:text-gray-400"
      >
        {{ $t('Back to history') }}
      </Link>
      <Pill :color="statusColors[run.status]" size="lg">
        {{ statuses[run.status] }}
      </Pill>
    </div>

    <dl
      class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 [&_dd]:text-gray-900 dark:[&_dd]:text-gray-100 [&_dt]:text-gray-500 dark:[&_dt]:text-gray-400"
    >
      <div>
        <dt>{{ $t('Started') }}</dt>
        <dd>{{ formatDateTime(run.created_at) }}</dd>
      </div>
      <div>
        <dt>{{ $t('Duration') }}</dt>
        <dd>{{ runDuration(run) }}</dd>
      </div>
      <div>
        <dt>{{ $t('Ticket') }}</dt>
        <dd>
          <Link
            v-if="run.ticket_id"
            :href="showTicket(run.ticket_id).url"
            class="hover:underline"
          >
            #{{ run.ticket_number }} {{ run.ticket_subject }}
          </Link>
          <template v-else>{{ $t('None') }}</template>
        </dd>
      </div>
      <div>
        <dt>{{ $t('Chain depth') }}</dt>
        <dd>{{ run.depth }}</dd>
      </div>
      <div v-if="event" class="sm:col-span-2">
        <dt>{{ $t('Triggered by') }}</dt>
        <dd>{{ event.description }}</dd>
      </div>
    </dl>

    <div
      v-if="run.status === 'waiting'"
      class="flex flex-wrap items-center gap-3 rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-900 dark:border-yellow-800 dark:bg-yellow-950 dark:text-yellow-100"
    >
      <span class="min-w-0 flex-1">
        <i18n-t keypath="Resumes at :time (:relative)." tag="span">
          <template #time>{{ formatDateTime(run.resume_at) }}</template>
          <template #relative>
            <RelativeTime :datetime="run.resume_at" />
          </template>
        </i18n-t>
      </span>
      <AppButton
        v-if="canCancel"
        type="button"
        size="xs"
        color="red"
        @click="cancelling = true"
      >
        {{ $t('Cancel run') }}
      </AppButton>
    </div>

    <div
      v-if="run.error"
      class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
      role="alert"
    >
      {{ run.error }}
    </div>

    <ul
      v-if="reasons.length || webhooks.length"
      class="space-y-1 text-sm text-gray-700 dark:text-gray-300"
    >
      <li v-for="(entry, i) in reasons" :key="`r${i}`">
        {{ entry.reason }}
      </li>
      <li v-for="(entry, i) in webhooks" :key="`w${i}`">
        {{
          $t('Webhook answered :status in :duration ms (attempt :attempt).', {
            status: String(entry.status ?? $t('nothing')),
            duration: String(entry.duration_ms),
            attempt: String(entry.attempt),
          })
        }}
        <span v-if="entry.error" class="text-red-600 dark:text-red-400">
          {{ entry.error }}
        </span>
      </li>
    </ul>

    <section v-if="run.payload" class="space-y-2">
      <h2 class="text-sm font-medium text-gray-900 dark:text-white">
        {{ $t('Webhook payload') }}
      </h2>
      <pre
        class="max-h-80 overflow-auto rounded-lg bg-gray-100 p-3 text-xs text-gray-800 dark:bg-gray-800 dark:text-gray-100"
        >{{ JSON.stringify(run.payload, null, 2) }}</pre>
    </section>

    <Deferred data="catalog">
      <template #fallback>
        <div
          class="animate-pulse space-y-3"
          :aria-label="$t('Loading the run')"
        >
          <div class="h-16 rounded-xl bg-gray-200 dark:bg-gray-800" />
          <div class="mx-auto h-10 w-px bg-gray-200 dark:bg-gray-800" />
          <div class="h-16 rounded-xl bg-gray-200 dark:bg-gray-800" />
        </div>
      </template>

      <div v-if="catalog" class="flex flex-col">
        <TriggerCard
          :model-value="definition.trigger"
          :open="openCard === TRIGGER_CARD"
          :catalog="catalog"
          :desks="desks"
          @update:open="openCard = $event ? TRIGGER_CARD : null"
        />

        <StepList :model-value="definition.steps" />
      </div>
    </Deferred>
  </div>

  <ConfirmationModal
    v-if="cancelling"
    :loading="cancelLoading"
    @close="cancelling = false"
    @confirmed="cancelRun"
  >
    <template #headline>{{ $t('Cancel this run?') }}</template>
    {{ $t('The steps after the delay will not run.') }}
    <template #actionText>{{ $t('Cancel run') }}</template>
  </ConfirmationModal>
</template>
