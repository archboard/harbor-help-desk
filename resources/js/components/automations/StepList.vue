<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed, inject } from 'vue';
import { SelectField } from '@/components/forms/fields';
import SortableList from '@/components/SortableList.vue';
import { notify } from '@/lib/notifications';
import { actionEditors } from './actions';
import AddStepMenu from './AddStepMenu.vue';
import { builderKey } from './builder';
import ConditionGroup from './ConditionGroup.vue';
import { MAX_DEPTH } from './conditions';
import { defaultConfig } from './configRules';
import ConfigForm from './ConfigForm.vue';
import DelayStep from './DelayStep.vue';
import { containsStep, errorsUnder } from './errors';
import PathsStep from './PathsStep.vue';
import StepCard from './StepCard.vue';
import { newStep, stepTypes, unknownStepIcon } from './steps';
import type { Step, StepType } from './steps';
import { cancelSummary, configSummary, summarizeStep } from './summaries';
import { noteOf, statusOf } from './testResult';

/**
 * A list of step cards joined by connectors with a "+" between them: the steps of an
 * automation, or of one branch of a paths step, which nests another of these. A stop
 * stays last. Everything else it needs comes from the builder page.
 */
const { depth = 1, parentId } = defineProps<{
  /** How deeply the list is nested; paths are not offered past the server's limit. */
  depth?: number;
  /** The paths step the list belongs to, which stays open when one of its cards closes. */
  parentId?: string;
}>();

const steps = defineModel<Step[]>({ required: true });

const {
  catalog,
  trigger,
  groups,
  names,
  desks,
  moveDesks,
  schedules,
  macros,
  slaPolicies,
  scopeDeskId,
  errors,
  problems,
  testLog,
  readonly,
  openCard,
} = inject(builderKey)!;

const allowPaths = computed(() => depth <= MAX_DEPTH);

const invalidCards = computed(() =>
  steps.value
    .filter((step) =>
      problems.value.some(
        (problem) => problem.cardId && containsStep([step], problem.cardId),
      ),
    )
    .map((step) => step.id),
);

const messagesFor = (cardId: string): string[] =>
  problems.value
    .filter((problem) => problem.cardId === cardId && !problem.inline)
    .map((problem) => problem.message);

/** A paths card stays open while one of the cards inside it is. */
const isOpen = (step: Step): boolean =>
  openCard.value !== null && containsStep([step], openCard.value);

const setOpen = (step: Step, open: boolean): void => {
  openCard.value = open ? step.id : (parentId ?? null);
};

const catalogAction = (key: string) =>
  catalog.value.actions.find((action) => action.key === key);

const actionOptions = computed(() =>
  catalog.value.actions.map((action) => ({
    value: action.key,
    label: action.label,
  })),
);

function detailsFor(step: Step): string | undefined {
  if (step.type === 'delay') {
    return cancelSummary(step, catalog.value);
  }

  if (step.type !== 'action' || actionEditors[step.action]) {
    return undefined;
  }

  const action = catalogAction(step.action);

  return action
    ? configSummary(step.config, action.config_rules, action.config_options, {
        desk_id: Object.fromEntries(
          desks.value.map((desk) => [desk.id, desk.name]),
        ),
      })
    : undefined;
}

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
      title: trans('Step deleted'),
      text: summarizeStep(step, catalog.value, names.value),
      action: {
        label: trans('Undo'),
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
</script>

<template>
  <div class="flex flex-col">
    <SortableList class="flex flex-col" @reorder="dropStep">
      <div v-for="(step, i) in steps" :key="step.id" class="flex flex-col">
        <AddStepMenu
          v-if="!readonly"
          :allow-paths="allowPaths"
          @add="addStep(i, $event)"
        />

        <StepCard
          :open="isOpen(step)"
          :icon="stepTypes[step.type]?.icon ?? unknownStepIcon"
          :kind="stepTypes[step.type]?.label ?? $t('Step')"
          :summary="summarizeStep(step, catalog, names)"
          :details="detailsFor(step)"
          :invalid="invalidCards.includes(step.id)"
          :errors="messagesFor(step.id)"
          :movable="!readonly && step.type !== 'stop'"
          :removable="!readonly"
          :status="testLog ? statusOf(testLog.get(step.id)) : undefined"
          :note="testLog ? noteOf(testLog.get(step.id)) : undefined"
          :can-move-up="i > 0"
          :can-move-down="i < steps.length - 1 && steps[i + 1].type !== 'stop'"
          @update:open="setOpen(step, $event)"
          @move="moveStep(i, $event)"
          @remove="removeStep(i)"
        >
          <fieldset
            :disabled="readonly && step.type !== 'paths'"
            class="min-w-0"
          >
            <div v-if="step.type === 'action'" class="space-y-4">
              <SelectField
                :model-value="step.action || null"
                :label="$t('Action')"
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
                :trigger="trigger"
                :desks="desks"
                :move-desks="moveDesks"
                :schedules="schedules"
                :macros="macros"
                :sla-policies="slaPolicies"
                :scope-desk-id="scopeDeskId"
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
                  errorsUnder(errors, `definition.steps.${step.id}.config.`)
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
              :result="testLog?.get(step.id)?.condition"
              :catalog="catalog"
              :groups="groups"
              :schedules="schedules"
              :errors="errorsUnder(errors, `definition.steps.${step.id}.`)"
            />
            <DelayStep
              v-else-if="step.type === 'delay'"
              :model-value="step"
              :catalog="catalog"
              :groups="groups"
              :schedules="schedules"
              :errors="errorsUnder(errors, `definition.steps.${step.id}.`)"
            />
            <PathsStep
              v-else-if="step.type === 'paths'"
              :model-value="step"
              :catalog="catalog"
              :groups="groups"
              :schedules="schedules"
              :depth="depth + 1"
              :errors="errorsUnder(errors, `definition.steps.${step.id}.`)"
            />
            <p
              v-else-if="step.type === 'stop'"
              class="text-sm text-gray-500 dark:text-gray-400"
            >
              {{
                $t(
                  'Ends this run and skips any later automations for this event.',
                )
              }}
            </p>
            <p v-else class="text-sm text-gray-500 dark:text-gray-400">
              {{
                $t(
                  "This step can't be edited here yet. It is kept as it is when you save, and you can move or delete it.",
                )
              }}
            </p>
          </fieldset>
        </StepCard>
      </div>
    </SortableList>

    <AddStepMenu
      v-if="!readonly && steps.at(-1)?.type !== 'stop'"
      allow-stop
      :allow-paths="allowPaths"
      @add="addStep(steps.length, $event)"
    />
  </div>
</template>
