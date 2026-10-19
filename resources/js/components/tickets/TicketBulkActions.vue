<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppInput from '@/components/forms/AppInput.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import FieldError from '@/components/forms/FieldError.vue';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import { bulk } from '@/routes/tickets';
import type { Desk } from '@/types';

/**
 * Applies one change to the selected tickets. Each change is sent on its own, all or
 * nothing, so the bar resets after every one.
 */
const { ticketIds, agents } = defineProps<{
  ticketIds: string[];
  canDelete: boolean;
  desks: Desk[];
  agents: App.Data.Users.UserData[];
  statuses: Record<string, string>;
  priorities: Record<string, string>;
}>();

const emit = defineEmits<{ clear: [] }>();

const processing = ref(false);
const error = ref<string | null>(null);
const newTag = ref('');
const deleting = ref(false);

const assigneeOptions = computed(() => [
  { value: 'none', label: 'Nobody' },
  ...agents.map((agent) => ({ value: agent.id, label: agent.name })),
]);

function apply(
  change: Record<string, string | string[] | boolean>,
  onSuccess?: () => void,
): void {
  router.post(
    bulk().url,
    { ticket_ids: ticketIds, ...change },
    {
      preserveScroll: true,
      onStart: () => (processing.value = true),
      onFinish: () => (processing.value = false),
      onSuccess: () => {
        error.value = null;
        onSuccess?.();
        emit('clear');
      },
      onError: (errors) => (error.value = Object.values(errors)[0] ?? null),
    },
  );
}

function assign(value: string | number | boolean | null | undefined): void {
  if (value) {
    apply({ assignee_ids: value === 'none' ? [] : [String(value)] });
  }
}

function addTag(): void {
  const name = newTag.value.trim();

  if (name) {
    apply({ add_tag: name }, () => (newTag.value = ''));
  }
}
</script>

<template>
  <div
    class="sticky bottom-4 z-10 space-y-3 rounded-lg bg-white p-3 shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
    role="region"
    aria-label="Bulk actions"
  >
    <div class="flex items-center justify-between gap-2">
      <p class="text-sm font-medium">
        {{ ticketIds.length }} selected
        <button
          type="button"
          class="ml-1 text-gray-500 hover:underline dark:text-gray-400"
          @click="emit('clear')"
        >
          Clear
        </button>
      </p>
      <AppButton
        v-if="canDelete"
        type="button"
        color="red"
        :disabled="processing"
        @click="deleting = true"
      >
        Delete
      </AppButton>
    </div>

    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
      <AppSelect
        :model-value="null"
        :options="statuses"
        null-option="Set status…"
        aria-label="Set status"
        :disabled="processing"
        @update:model-value="$event && apply({ status: String($event) })"
      />
      <AppSelect
        :model-value="null"
        :options="priorities"
        null-option="Set priority…"
        aria-label="Set priority"
        :disabled="processing"
        @update:model-value="$event && apply({ priority: String($event) })"
      />
      <AppSelect
        :model-value="null"
        :options="assigneeOptions"
        null-option="Assign to…"
        aria-label="Assign to"
        :disabled="processing"
        @update:model-value="assign"
      />
      <AppSelect
        :model-value="null"
        :options="desks"
        null-option="Move to…"
        aria-label="Move to desk"
        :disabled="processing"
        @update:model-value="$event && apply({ desk_id: String($event) })"
      />
      <form class="flex gap-2" @submit.prevent="addTag">
        <AppInput
          v-model="newTag"
          placeholder="Add tag"
          aria-label="Add a tag"
          class="flex-1"
          :disabled="processing"
        />
        <AppButton
          type="submit"
          color="white"
          :disabled="processing || !newTag.trim()"
        >
          Add
        </AppButton>
      </form>
    </div>

    <FieldError v-if="error">{{ error }}</FieldError>

    <ConfirmationModal
      v-if="deleting"
      :loading="processing"
      @close="deleting = false"
      @confirmed="(close) => apply({ delete: true }, close)"
    >
      <template #headline>Delete {{ ticketIds.length }} tickets?</template>
      They move to the Deleted list, where a desk lead can restore them.
      <template #actionText>Delete tickets</template>
    </ConfirmationModal>
  </div>
</template>
