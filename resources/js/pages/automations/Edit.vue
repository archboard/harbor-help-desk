<script setup lang="ts">
import {
  Deferred,
  Head,
  router,
  setLayoutProps,
  useForm,
} from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import { computed, onBeforeUnmount, ref } from 'vue';
import AddStepMenu from '@/components/automations/AddStepMenu.vue';
import {
  cardErrors,
  errorsUnder,
  TRIGGER_CARD,
} from '@/components/automations/errors';
import ConditionGroup from '@/components/automations/ConditionGroup.vue';
import { availableGroups } from '@/components/automations/conditions';
import { defaultConfig } from '@/components/automations/configRules';
import { actionEditors } from '@/components/automations/actions';
import ConfigForm from '@/components/automations/ConfigForm.vue';
import StepCard from '@/components/automations/StepCard.vue';
import {
  newStep,
  stepTypes,
  unknownStepIcon,
} from '@/components/automations/steps';
import type {
  Definition,
  Step,
  StepType,
} from '@/components/automations/steps';
import {
  configSummary,
  summarizeStep,
} from '@/components/automations/summaries';
import TriggerCard from '@/components/automations/TriggerCard.vue';
import AppButton from '@/components/AppButton.vue';
import SortableList from '@/components/SortableList.vue';
import {
  InputField,
  SelectField,
  TextareaField,
} from '@/components/forms/fields';
import { notify } from '@/lib/notifications';
import { index, store, update } from '@/routes/automations';

type Automation = App.Data.Automations.AutomationData;

const { automation, desks, schedules, canScopeToWorkspace, catalog } =
  defineProps<{
    automation: Automation | null;
    desks: App.Data.Desks.DeskData[];
    schedules: App.Data.BusinessSchedules.BusinessScheduleData[];
    canScopeToWorkspace: boolean;
    /** Deferred. */
    catalog?: App.Data.Automations.AutomationCatalogData;
  }>();

defineOptions({ layout: { title: '' } });

setLayoutProps({ title: automation ? 'Edit automation' : 'New automation' });

const form = useForm({
  name: automation?.name ?? '',
  description: automation?.description ?? '',
  desk_id:
    automation?.desk_id ?? (canScopeToWorkspace ? '' : (desks[0]?.id ?? '')),
  // Typed loosely: the form helper can't type the recursive steps.
  definition: structuredClone(
    automation?.definition ?? {
      trigger: { type: 'ticket.created', config: {} },
      steps: [],
    },
  ) as never,
});

/** The form's `definition`, typed. It is the same reactive object. */
const definition = form.definition as unknown as Definition;
const errors: Record<string, string> = form.errors;

/** The card that is expanded: the trigger, a step id, or none. */
const openCard = ref<string | null>(automation ? null : TRIGGER_CARD);

const steps = computed(() => definition.steps);
const problems = computed(() => cardErrors(errors, steps.value));
const invalidCards = computed(
  () => new Set(problems.value.map((problem) => problem.cardId)),
);

const messagesFor = (cardId: string): string[] =>
  problems.value
    .filter((problem) => problem.cardId === cardId && !problem.inline)
    .map((problem) => problem.message);

/** The field groups the trigger makes available to rules. */
const fieldGroups = computed(() =>
  catalog ? availableGroups(catalog, definition.trigger.type) : [],
);

const scopeOptions = computed(() => [
  ...(canScopeToWorkspace ? [{ value: '', label: 'Whole workspace' }] : []),
  ...desks.map((desk) => ({ value: desk.id, label: desk.name })),
]);

const actionOptions = computed(() =>
  (catalog?.actions ?? []).map((action) => ({
    value: action.key,
    label: action.label,
  })),
);

/** The problems that fit no card (a step limit, say), then one line per invalid card. */
const summaryLines = computed(() =>
  problems.value.map((problem) => {
    const index = steps.value.findIndex((step) => step.id === problem.cardId);

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

function addStep(index: number, type: StepType): void {
  const step = newStep(type);

  steps.value.splice(index, 0, step);
  openCard.value = step.id;
}

function moveStep(index: number, offset: -1 | 1): void {
  const [step] = steps.value.splice(index, 1);

  steps.value.splice(index + offset, 0, step);
}

/** A step was dragged. A stop stays last, so nothing is dropped after one. */
function dropStep(from: number, to: number): void {
  const last = steps.value.length - 1;

  if (
    steps.value[from]?.type === 'stop' ||
    (steps.value[last]?.type === 'stop' && to >= last)
  ) {
    return;
  }

  steps.value.splice(to, 0, ...steps.value.splice(from, 1));
}

function removeStep(index: number): void {
  const [step] = steps.value.splice(index, 1);

  notify(
    {
      level: 'neutral',
      title: 'Step deleted',
      text: summarizeStep(step, catalog!),
      action: {
        label: 'Undo',
        onClick: () =>
          steps.value.splice(Math.min(index, steps.value.length), 0, step),
      },
    },
    8000,
  );
}

function setAction(
  step: Step,
  key: string | number | boolean | null | undefined,
): void {
  if (step.type !== 'action' || step.action === key) {
    return;
  }

  step.action = String(key ?? '');

  const action = catalogAction(step.action);

  step.config = defaultConfig(
    action?.config_rules ?? {},
    action?.config_options,
  );
}

const catalogAction = (key: string) =>
  catalog?.actions.find((action) => action.key === key);

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

      <div v-if="catalog" class="flex flex-col">
        <TriggerCard
          v-model="definition.trigger"
          :open="openCard === TRIGGER_CARD"
          :catalog="catalog"
          :desks="desks"
          :invalid="invalidCards.has(TRIGGER_CARD)"
          :messages="messagesFor(TRIGGER_CARD)"
          :errors="errorsUnder(errors, 'definition.trigger.config.')"
          @update:open="openCard = $event ? TRIGGER_CARD : null"
        />

        <SortableList class="flex flex-col" @reorder="dropStep">
          <div v-for="(step, i) in steps" :key="step.id" class="flex flex-col">
            <AddStepMenu @add="addStep(i, $event)" />

            <StepCard
              :open="openCard === step.id"
              :icon="stepTypes[step.type]?.icon ?? unknownStepIcon"
              :kind="stepTypes[step.type]?.label ?? 'Step'"
              :summary="summarizeStep(step, catalog)"
              :details="
                step.type === 'action' &&
                step.action !== 'set_field' &&
                catalogAction(step.action)
                  ? configSummary(
                      step.config,
                      catalogAction(step.action)!.config_rules,
                      catalogAction(step.action)!.config_options,
                      {
                        desk_id: Object.fromEntries(
                          desks.map((desk) => [desk.id, desk.name]),
                        ),
                      },
                    )
                  : undefined
              "
              :invalid="invalidCards.has(step.id)"
              :errors="messagesFor(step.id)"
              :movable="step.type !== 'stop'"
              removable
              :can-move-up="i > 0"
              :can-move-down="
                i < steps.length - 1 && steps[i + 1].type !== 'stop'
              "
              @update:open="openCard = $event ? step.id : null"
              @move="moveStep(i, $event)"
              @remove="removeStep(i)"
            >
              <div v-if="step.type === 'action'" class="space-y-4">
                <SelectField
                  :model-value="step.action || null"
                  label="Action"
                  required
                  :options="actionOptions"
                  :error="errors[`definition.steps.${step.id}.action`]"
                  @update:model-value="setAction(step, $event)"
                />
                <component
                  :is="actionEditors[step.action]"
                  v-if="actionEditors[step.action]"
                  v-model="step.config"
                  :catalog="catalog"
                  :errors="
                    errorsUnder(errors, `definition.steps.${step.id}.config.`)
                  "
                />
                <ConfigForm
                  v-else
                  v-model="step.config"
                  :rules="catalogAction(step.action)?.config_rules ?? {}"
                  :options="catalogAction(step.action)?.config_options"
                  :errors="
                    errorsUnder(
                      form.errors,
                      `definition.steps.${step.id}.config.`,
                    )
                  "
                  :choices="{
                    desk_id: desks.map((desk) => ({
                      value: desk.id,
                      label: desk.name,
                    })),
                  }"
                />
              </div>
              <ConditionGroup
                v-else-if="step.type === 'condition'"
                :model-value="step"
                :catalog="catalog"
                :groups="fieldGroups"
                :schedules="schedules"
                :errors="errorsUnder(errors, `definition.steps.${step.id}.`)"
              />
              <p
                v-else-if="step.type === 'stop'"
                class="text-sm text-gray-500 dark:text-gray-400"
              >
                Ends this run and skips any later automations for this event.
              </p>
              <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                This step can't be edited here yet. It is kept as it is when you
                save, and you can move or delete it.
              </p>
            </StepCard>
          </div>
        </SortableList>

        <AddStepMenu
          v-if="steps.at(-1)?.type !== 'stop'"
          allow-stop
          @add="addStep(steps.length, $event)"
        />
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
</template>
