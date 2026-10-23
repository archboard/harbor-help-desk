<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { computed, inject } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppInput from '@/components/forms/AppInput.vue';
import FieldError from '@/components/forms/FieldError.vue';
import { notify } from '@/lib/notifications';
import ConditionGroup from './ConditionGroup.vue';
import { builderKey } from './builder';
import { nodeKey } from './conditions';
import { errorsUnder } from './errors';
import { newBranch } from './steps';
import StepList from './StepList.vue';

type Paths = App.Data.Automations.PathsStepData;

/**
 * Paths: the first branch whose condition matches runs its steps, and "Otherwise" runs when
 * none does. Branches are stacked, each with a step list of its own.
 */
const {
  catalog,
  groups,
  schedules,
  depth,
  errors = {},
} = defineProps<{
  catalog: App.Data.Automations.AutomationCatalogData;
  groups: App.Automations.FieldGroup[];
  schedules: App.Data.BusinessSchedules.BusinessScheduleData[];
  /** The nesting level of the step lists inside: one deeper than the paths step's own. */
  depth: number;
  /** Server errors relative to the step: `branches.0.name`, `branches.0.condition.rules.0.value`. */
  errors?: Record<string, string>;
}>();

const step = defineModel<Paths>({ required: true });

const { testLog } = inject(builderKey)!;

/** What the test run did here: the index of the path it took, or 'else'. */
const taken = computed(() => {
  const entry = testLog.value?.get(step.value.id);

  return entry?.branch === 'else' ? 'else' : entry?.branch_index;
});

const TAKEN = 'border-green-500 ring-1 ring-green-500 dark:border-green-500';

function removeBranch(index: number): void {
  const [branch] = step.value.branches.splice(index, 1);

  notify(
    {
      level: 'neutral',
      title: 'Path deleted',
      text: branch.name,
      action: {
        label: 'Undo',
        onClick: () =>
          step.value.branches.splice(
            Math.min(index, step.value.branches.length),
            0,
            branch,
          ),
      },
    },
    8000,
  );
}
</script>

<template>
  <div class="space-y-3">
    <div class="space-y-4">
      <section
        v-for="(branch, index) in step.branches"
        :key="nodeKey(branch)"
        class="min-w-0 space-y-3 rounded-lg border p-3"
        :class="
          taken === index ? TAKEN : 'border-gray-200 dark:border-gray-700'
        "
      >
        <div class="flex items-start gap-2">
          <div class="min-w-0 flex-1 space-y-1">
            <AppInput
              v-model="branch.name"
              aria-label="Path name"
              :has-error="!!errors[`branches.${index}.name`]"
            />
            <FieldError v-if="errors[`branches.${index}.name`]">
              {{ errors[`branches.${index}.name`] }}
            </FieldError>
          </div>
          <AppButton
            v-if="step.branches.length > 1"
            type="button"
            size="xs"
            color="red"
            class="mt-1"
            @click="removeBranch(index)"
          >
            <Trash2 class="size-3" aria-hidden="true" />
            <span class="sr-only">Delete path</span>
          </AppButton>
        </div>

        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
          If the ticket matches
        </p>
        <ConditionGroup
          v-model="branch.condition"
          :catalog="catalog"
          :groups="groups"
          :schedules="schedules"
          :errors="errorsUnder(errors, `branches.${index}.condition.`)"
          :result="
            taken === index ? testLog?.get(step.id)?.condition : undefined
          "
        />

        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Then</p>
        <StepList v-model="branch.steps" :depth="depth" :parent-id="step.id" />
      </section>

      <section
        class="min-w-0 space-y-3 rounded-lg border border-dashed p-3"
        :class="
          taken === 'else' ? TAKEN : 'border-gray-300 dark:border-gray-600'
        "
      >
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Otherwise
        </p>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Runs when no path above matches.
        </p>
        <StepList v-model="step.else" :depth="depth" :parent-id="step.id" />
      </section>
    </div>

    <AppButton
      type="button"
      size="xs"
      color="white"
      @click="step.branches.push(newBranch(step.branches.length))"
    >
      <Plus class="size-3" aria-hidden="true" />
      Path
    </AppButton>
  </div>
</template>
