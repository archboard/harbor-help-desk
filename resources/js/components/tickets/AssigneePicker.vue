<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import GenericObjectCombobox from '@/components/forms/GenericObjectCombobox.vue';
import Pill from '@/components/Pill.vue';

/**
 * Assignees as removable pills, plus a search box over the desk members not yet assigned.
 */
const { members, disabled = false } = defineProps<{
  members: Pick<App.Data.Desks.DeskMemberData, 'id' | 'name'>[];
  disabled?: boolean;
}>();

const ids = defineModel<string[]>({ required: true });

const assigned = computed(() =>
  ids.value
    .map((id) => members.find((member) => member.id === id))
    .filter((member) => member !== undefined),
);
const assignable = computed(() =>
  members.filter((member) => !ids.value.includes(member.id)),
);

const searchId = useId();
/** Bumped after adding someone, to clear the search box. */
const searchKey = ref(0);

function add(
  member: Pick<App.Data.Desks.DeskMemberData, 'id' | 'name'> | null,
): void {
  if (member) {
    ids.value = [...ids.value, member.id];
    searchKey.value++;
  }
}

function remove(id: string): void {
  ids.value = ids.value.filter((assignee) => assignee !== id);
}
</script>

<template>
  <div class="space-y-2">
    <div v-if="assigned.length" class="flex flex-wrap gap-1">
      <Pill v-for="member in assigned" :key="member.id" color="primary">
        {{ member.name }}
        <button
          v-if="!disabled"
          type="button"
          class="-mr-1 ml-1 rounded-full hover:text-red-600"
          @click="remove(member.id)"
        >
          <span class="sr-only">{{
            $t('Unassign :name', { name: member.name })
          }}</span>
          <X class="size-3" aria-hidden="true" />
        </button>
      </Pill>
    </div>
    <p v-else-if="disabled" class="text-sm text-gray-400">
      {{ $t('Unassigned') }}
    </p>

    <div v-if="!disabled && assignable.length">
      <label :for="searchId" class="sr-only">{{ $t('Assign someone') }}</label>
      <GenericObjectCombobox
        :id="searchId"
        :key="searchKey"
        :options="assignable"
        @update:model-value="add"
      />
    </div>
    <p v-else-if="!disabled && !members.length" class="text-sm text-gray-400">
      {{ $t('Nobody is on this desk yet.') }}
    </p>
  </div>
</template>
