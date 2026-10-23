<script setup lang="ts">
import { GripVertical, Plus, Trash2 } from '@lucide/vue';
import AppButton from '@/components/AppButton.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import FieldError from '@/components/forms/FieldError.vue';
import SortableList from '@/components/SortableList.vue';
import ConditionRule from './ConditionRule.vue';
import { MAX_DEPTH, newGroup, newRule, nodeKey } from './conditions';
import type { Group, Node, Rule } from './conditions';
import { errorsUnder } from './errors';
import type { GroupResult, RuleResult } from './testResult';

/**
 * A match mode (all or any) with its rules and nested groups, which can be dragged to reorder.
 * Groups nest up to the server's limit.
 */
const {
  catalog,
  groups,
  schedules = [],
  errors = {},
  depth = 1,
  removable = false,
  result,
} = defineProps<{
  catalog: App.Data.Automations.AutomationCatalogData;
  groups: App.Automations.FieldGroup[];
  schedules?: App.Data.BusinessSchedules.BusinessScheduleData[];
  /** Server errors relative to the group: `match`, `rules` and `rules.0.value`. */
  errors?: Record<string, string>;
  depth?: number;
  /** Shows a remove button, for a nested group. */
  removable?: boolean;
  /** What a test run found for this group, in the order of its rules. */
  result?: GroupResult;
}>();

const emit = defineEmits<{ remove: [] }>();

const group = defineModel<Group>({ required: true });

const groupError = (): string | undefined =>
  errors.match ?? errors.rules ?? errors[''];

function move(from: number, to: number): void {
  group.value.rules.splice(to, 0, ...group.value.rules.splice(from, 1));
}
</script>

<template>
  <div
    class="space-y-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700"
    :class="{ 'bg-gray-50 dark:bg-gray-800/40': depth % 2 === 0 }"
  >
    <div class="flex flex-wrap items-center gap-2">
      <span class="text-sm text-gray-700 dark:text-gray-300">Match</span>
      <div class="w-28">
        <AppSelect
          hide-null
          aria-label="Match mode"
          :options="[
            { value: 'all', label: 'all rules' },
            { value: 'any', label: 'any rule' },
          ]"
          :model-value="group.match"
          @update:model-value="group.match = $event as Group['match']"
        />
      </div>
      <AppButton
        v-if="removable"
        type="button"
        size="xs"
        color="red"
        class="ms-auto"
        @click="emit('remove')"
      >
        <Trash2 class="size-3" aria-hidden="true" />
        <span class="sr-only">Remove group</span>
      </AppButton>
    </div>

    <p
      v-if="!group.rules.length"
      class="text-sm text-gray-500 dark:text-gray-400"
    >
      No rules yet. A condition without rules always passes.
    </p>

    <SortableList class="space-y-3" @reorder="move">
      <div
        v-for="(node, index) in group.rules"
        :key="nodeKey(node)"
        class="flex items-start gap-2"
      >
        <GripVertical
          class="drag-handle mt-2 size-4 shrink-0 cursor-grab text-gray-400"
          aria-hidden="true"
        />
        <ConditionGroup
          v-if="node.type === 'group'"
          v-model="group.rules[index] as Node & Group"
          class="min-w-0 flex-1"
          :catalog="catalog"
          :groups="groups"
          :schedules="schedules"
          :errors="errorsUnder(errors, `rules.${index}.`)"
          :result="result?.rules[index] as GroupResult | undefined"
          :depth="depth + 1"
          removable
          @remove="group.rules.splice(index, 1)"
        />
        <template v-else>
          <ConditionRule
            v-model="group.rules[index] as Rule"
            :catalog="catalog"
            :groups="groups"
            :schedules="schedules"
            :errors="errorsUnder(errors, `rules.${index}.`)"
            :result="result?.rules[index] as RuleResult | undefined"
          />
          <AppButton
            type="button"
            size="xs"
            color="red"
            class="mt-1"
            @click="group.rules.splice(index, 1)"
          >
            <Trash2 class="size-3" aria-hidden="true" />
            <span class="sr-only">Remove rule</span>
          </AppButton>
        </template>
      </div>
    </SortableList>

    <FieldError v-if="groupError()">{{ groupError() }}</FieldError>

    <div class="flex gap-2">
      <AppButton
        type="button"
        size="xs"
        color="white"
        @click="group.rules.push(newRule())"
      >
        <Plus class="size-3" aria-hidden="true" />
        Rule
      </AppButton>
      <AppButton
        v-if="depth < MAX_DEPTH"
        type="button"
        size="xs"
        color="white"
        @click="group.rules.push(newGroup())"
      >
        <Plus class="size-3" aria-hidden="true" />
        Group
      </AppButton>
    </div>
  </div>
</template>
