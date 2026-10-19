<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppTextarea from '@/components/forms/AppTextarea.vue';
import FieldError from '@/components/forms/FieldError.vue';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Pill from '@/components/Pill.vue';
import AttachmentLink from '@/components/tickets/AttachmentLink.vue';
import { formatDateTime, timeAgo } from '@/lib/dates';
import { destroy, update } from '@/routes/comments';
import type { TicketComment } from '@/types';

const { comment } = defineProps<{
  comment: TicketComment;
  canEdit: boolean;
  canDelete: boolean;
}>();

const editing = ref(false);
const form = useForm({ body: comment.body });

const deleting = ref(false);
const deleteLoading = ref(false);

function startEditing(): void {
  form.defaults({ body: comment.body });
  form.reset();
  form.clearErrors();
  editing.value = true;
}

function save(): void {
  form.submit(update(comment.id), {
    preserveScroll: true,
    onSuccess: () => (editing.value = false),
  });
}

function remove(close: () => void): void {
  router.delete(destroy(comment.id).url, {
    preserveScroll: true,
    onStart: () => (deleteLoading.value = true),
    onFinish: () => (deleteLoading.value = false),
    onSuccess: close,
  });
}
</script>

<template>
  <article
    :class="[
      'rounded-2xl p-5 shadow-sm',
      comment.is_internal
        ? 'bg-yellow-50 ring-1 ring-yellow-200 dark:bg-yellow-950 dark:ring-yellow-900'
        : 'bg-white dark:bg-gray-900',
    ]"
  >
    <header class="mb-2 flex flex-wrap items-center gap-2 text-sm">
      <span class="font-medium">{{ comment.author_name ?? 'Unknown' }}</span>
      <Pill v-if="comment.is_internal" color="yellow">Internal</Pill>
      <span
        class="text-gray-500 dark:text-gray-400"
        :title="formatDateTime(comment.created_at)"
      >
        {{ timeAgo(comment.created_at) }}
      </span>
      <span
        v-if="comment.edited_at"
        class="text-gray-400 dark:text-gray-500"
        :title="formatDateTime(comment.edited_at)"
      >
        (edited)
      </span>
      <span v-if="!editing" class="ml-auto flex gap-1">
        <AppButton
          v-if="canEdit"
          type="button"
          size="xs"
          color="white"
          @click="startEditing"
        >
          Edit
        </AppButton>
        <AppButton
          v-if="canDelete"
          type="button"
          size="xs"
          color="white"
          @click="deleting = true"
        >
          Delete
        </AppButton>
      </span>
    </header>

    <form v-if="editing" class="space-y-2" @submit.prevent="save">
      <AppTextarea
        v-model="form.body"
        autofocus
        aria-label="Comment"
        :has-error="!!form.errors.body"
      />
      <FieldError v-if="form.errors.body">{{ form.errors.body }}</FieldError>
      <div class="flex gap-2">
        <AppButton type="submit" size="sm" :loading="form.processing">
          Save
        </AppButton>
        <AppButton
          type="button"
          size="sm"
          color="white"
          @click="editing = false"
        >
          Cancel
        </AppButton>
      </div>
    </form>
    <p v-else class="text-sm whitespace-pre-wrap">{{ comment.body }}</p>

    <ul v-if="comment.attachments?.length" class="mt-3 space-y-1 text-sm">
      <li v-for="attachment in comment.attachments" :key="attachment.id">
        <AttachmentLink :attachment="attachment" />
      </li>
    </ul>

    <ConfirmationModal
      v-if="deleting"
      :loading="deleteLoading"
      @close="deleting = false"
      @confirmed="remove"
    >
      <template #headline>Delete this comment?</template>
      The comment and its files will no longer show on this ticket.
      <template #actionText>Delete comment</template>
    </ConfirmationModal>
  </article>
</template>
