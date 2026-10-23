<script setup lang="ts">
import {
  Deferred,
  Head,
  router,
  setLayoutProps,
  useForm,
} from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import {
  computed,
  onBeforeUnmount,
  provide,
  ref,
  toRaw,
  watch,
  watchEffect,
} from 'vue';
import { builderKey } from '@/components/automations/builder';
import {
  cardErrors,
  errorsUnder,
  TRIGGER_CARD,
} from '@/components/automations/errors';
import { availableGroups } from '@/components/automations/conditions';
import StepList from '@/components/automations/StepList.vue';
import type { Definition } from '@/components/automations/steps';
import { fieldFor } from '@/components/automations/summaries';
import TestPanel from '@/components/automations/TestPanel.vue';
import type { TestResult } from '@/components/automations/testResult';
import TriggerCard from '@/components/automations/TriggerCard.vue';
import AppButton from '@/components/AppButton.vue';
import {
  InputField,
  SelectField,
  TextareaField,
} from '@/components/forms/fields';
import { index, store, update } from '@/routes/automations';

type Automation = App.Data.Automations.AutomationData;

const {
  automation,
  desks,
  schedules,
  macros,
  moveDesks,
  slaPolicies,
  canScopeToWorkspace,
  catalog,
} = defineProps<{
  automation: Automation | null;
  desks: App.Data.Desks.DeskData[];
  schedules: App.Data.BusinessSchedules.BusinessScheduleData[];
  /** The desks a move action may target. */
  moveDesks: App.Data.Desks.DeskData[];
  /** The shared macros the user may apply. */
  macros: App.Data.Macros.MacroData[];
  slaPolicies: App.Data.SlaPolicies.SlaPolicyData[];
  canScopeToWorkspace: boolean;
  /** Deferred. */
  catalog?: App.Data.Automations.AutomationCatalogData;
}>();

defineOptions({ layout: { title: '' } });

// Again after the first save, which lands on the edit page without remounting this one.
watchEffect(() =>
  setLayoutProps({ title: automation ? 'Edit automation' : 'New automation' }),
);

const form = useForm({
  name: automation?.name ?? '',
  description: automation?.description ?? '',
  desk_id:
    automation?.desk_id ?? (canScopeToWorkspace ? '' : (desks[0]?.id ?? '')),
  // Typed loosely: the form helper can't type the recursive steps.
  // The prop is a reactive proxy, which structuredClone can't copy.
  definition: structuredClone(
    toRaw(automation?.definition) ?? {
      trigger: { type: 'ticket.created', config: {} },
      steps: [],
    },
  ) as never,
});

/** The form's `definition`, typed. It is the same reactive object. */
const definition = form.definition as unknown as Definition;

/** The latest test run. Any edit to the flow clears it, since it no longer describes it. */
const testing = ref(false);
const testResult = ref<TestResult | null>(null);

watch(definition, () => (testResult.value = null), { deep: true });

const testLog = computed(
  () => new Map(testResult.value?.log.map((entry) => [entry.step_id, entry])),
);

const testSummary = computed(() => {
  const result = testResult.value;

  if (!result) {
    return '';
  }

  if (result.error) {
    return `The test failed on an error: ${result.error}`;
  }

  if (result.trigger_matched === false) {
    return 'The trigger does not match the chosen event, so a real run would not start.';
  }

  if (result.stopped) {
    return 'The test reached a stop step.';
  }

  return result.matched
    ? 'All the steps that apply were run.'
    : 'A condition did not pass, so the steps after it did not run.';
});

/** The card that is expanded: the trigger, a step id, or none. */
const openCard = ref<string | null>(automation ? null : TRIGGER_CARD);

const steps = computed(() => definition.steps);
const problems = computed(() => cardErrors(form.errors, steps.value));
const invalidCards = computed(
  () => new Set(problems.value.map((problem) => problem.rootId)),
);

const messagesFor = (cardId: string): string[] =>
  problems.value
    .filter((problem) => problem.cardId === cardId && !problem.inline)
    .map((problem) => problem.message);

/** The field groups the trigger makes available to rules. */
const fieldGroups = computed(() =>
  catalog ? availableGroups(catalog, definition.trigger.type) : [],
);

/** Names by id, for the summaries of what an action points at. */
const names = computed<Record<string, string>>(() =>
  Object.fromEntries([
    ...[...desks, ...moveDesks].map((desk) => [desk.id, desk.name]),
    ...macros.map((macro) => [macro.id, macro.name]),
    ...slaPolicies.map((policy) => [policy.id, policy.name]),
    ...(catalog
      ? (fieldFor(catalog, 'ticket.assignees')?.options ?? [])
      : []
    ).map((user) => [user.value, user.label]),
  ]),
);

const scopeOptions = computed(() => [
  ...(canScopeToWorkspace ? [{ value: '', label: 'Whole workspace' }] : []),
  ...desks.map((desk) => ({ value: desk.id, label: desk.name })),
]);

/** The problems that fit no card (a step limit, say), then one line per invalid card. */
const summaryLines = computed(() =>
  problems.value.map((problem) => {
    const index = steps.value.findIndex((step) => step.id === problem.rootId);

    return {
      cardId: problem.cardId,
      text: `${
        problem.cardId === TRIGGER_CARD
          ? 'Trigger'
          : index === -1
            ? 'Automation'
            : `Step ${index + 1}`
      }: ${problem.message}`,
    };
  }),
);

provide(builderKey, {
  catalog: computed(() => catalog!),
  trigger: computed(() => definition.trigger.type),
  groups: fieldGroups,
  names,
  desks: computed(() => desks),
  moveDesks: computed(() => moveDesks),
  schedules: computed(() => schedules),
  macros: computed(() => macros),
  slaPolicies: computed(() => slaPolicies),
  scopeDeskId: computed(() => form.desk_id || null),
  errors: computed(() => form.errors),
  problems,
  testLog: computed(() => (testResult.value ? testLog.value : null)),
  openCard,
});

/** Whether the form is being submitted, which is not "leaving" even though it visits. */
let saving = false;

function save(enable = false): void {
  saving = true;

  form
    .transform((data) => ({
      ...data,
      description: data.description || null,
      desk_id: data.desk_id || null,
      ...(enable ? { enabled: true } : {}),
    }))
    [automation ? 'put' : 'post'](
      automation ? update(automation.id).url : store().url,
      {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
        onError: () => {
          openCard.value =
            problems.value.find((problem) => problem.cardId)?.cardId ??
            openCard.value;
        },
        onFinish: () => (saving = false),
      },
    );
}

// Partial reloads (the deferred catalog, realtime refreshes) stay on the page.
const stopBefore = router.on('before', (event) => {
  if (!form.isDirty || saving || event.detail.visit.only.length) {
    return;
  }

  return confirm('You have unsaved changes. Leave this page?');
});

onBeforeUnmount(stopBefore);

useEventListener(window, 'beforeunload', (event) => {
  if (form.isDirty) {
    event.preventDefault();
  }
});
</script>

<template>
  <Head :title="automation ? 'Edit automation' : 'New automation'" />

  <form class="mx-auto max-w-3xl space-y-8" @submit.prevent="save()">
    <div class="space-y-4">
      <InputField
        v-model="form.name"
        label="Name"
        required
        :error="form.errors.name"
      />
      <TextareaField
        v-model="form.description"
        label="Description"
        :error="form.errors.description"
      />
      <SelectField
        v-model="form.desk_id"
        label="Scope"
        :options="scopeOptions"
        :error="form.errors.desk_id"
        help-text="Who can edit this automation, and which desk's tickets it works on."
      />
    </div>

    <div
      v-if="summaryLines.length"
      class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
      role="alert"
    >
      <p class="font-medium">Fix these before saving:</p>
      <ul class="mt-2 list-disc space-y-1 ps-5">
        <li v-for="(line, i) in summaryLines" :key="i">
          <button
            v-if="line.cardId"
            type="button"
            class="text-start underline"
            @click="openCard = line.cardId"
          >
            {{ line.text }}
          </button>
          <template v-else>{{ line.text }}</template>
        </li>
      </ul>
    </div>

    <Deferred data="catalog">
      <template #fallback>
        <div class="animate-pulse space-y-3" aria-label="Loading the builder">
          <div class="h-16 rounded-xl bg-gray-200 dark:bg-gray-800" />
          <div class="mx-auto h-10 w-px bg-gray-200 dark:bg-gray-800" />
          <div class="h-16 rounded-xl bg-gray-200 dark:bg-gray-800" />
        </div>
      </template>

      <div
        v-if="testResult"
        class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border p-3 text-sm"
        :class="
          testResult.error
            ? 'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200'
            : 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-200'
        "
        role="status"
      >
        <span class="min-w-0 flex-1">{{ testSummary }}</span>
        <AppButton
          type="button"
          size="xs"
          color="white"
          @click="testResult = null"
        >
          Clear results
        </AppButton>
      </div>

      <div v-if="catalog" class="flex flex-col">
        <TriggerCard
          v-model="definition.trigger"
          :open="openCard === TRIGGER_CARD"
          :catalog="catalog"
          :desks="desks"
          :invalid="invalidCards.has(TRIGGER_CARD)"
          :messages="messagesFor(TRIGGER_CARD)"
          :errors="errorsUnder(form.errors, 'definition.trigger.config.')"
          :automation="automation"
          :test-result="testResult"
          @update:open="openCard = $event ? TRIGGER_CARD : null"
        />

        <StepList v-model="definition.steps" />
      </div>
    </Deferred>

    <div
      class="sticky bottom-0 -mx-4 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 bg-white/90 px-4 py-3 backdrop-blur dark:border-gray-800 dark:bg-gray-950/90"
    >
      <span
        v-if="form.isDirty"
        class="me-auto text-sm text-gray-500 dark:text-gray-400"
      >
        Unsaved changes
      </span>
      <AppButton type="button" color="white" @click="testing = true">
        Test
      </AppButton>
      <AppButton type="button" color="white" @click="router.visit(index().url)">
        {{ form.isDirty ? 'Discard' : 'Back' }}
      </AppButton>
      <AppButton
        type="submit"
        color="white"
        :loading="form.processing"
        :disabled="!form.isDirty && !!automation"
      >
        Save
      </AppButton>
      <AppButton
        v-if="!automation?.enabled"
        type="button"
        :loading="form.processing"
        @click="save(true)"
      >
        Save &amp; enable
      </AppButton>
    </div>
  </form>

  <TestPanel
    v-model:open="testing"
    :definition="definition"
    :desk-id="form.desk_id || null"
    :webhook="definition.trigger.type === 'webhook'"
    @result="testResult = $event"
  />
</template>
