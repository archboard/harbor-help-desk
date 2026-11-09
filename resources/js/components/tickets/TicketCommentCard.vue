<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppTextarea from '@/components/forms/AppTextarea.vue';
import FieldError from '@/components/forms/FieldError.vue';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Pill from '@/components/Pill.vue';
import AttachmentLink from '@/components/tickets/AttachmentLink.vue';
import RelativeTime from '@/components/RelativeTime.vue';
import { formatDateTime } from '@/lib/dates';
import { destroy, update } from '@/routes/comments';

const { comment } = defineProps<{
  comment: App.Data.Tickets.TicketCommentData;
  canEdit: boolean;
  canDelete: boolean;
}>();

const editing = ref(false);
const form = useForm({
  body: comment.body,
} satisfies App.Data.Tickets.UpdateTicketCommentData);

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
      'rounded-md p-3 ring-1 ring-inset',
      comment.is_internal
        ? 'bg-yellow-50 ring-yellow-300 dark:bg-yellow-950 dark:ring-yellow-800'
        : 'bg-white ring-gray-200 dark:bg-gray-900 dark:ring-gray-700',
    ]"
  >
    <header class="flex flex-wrap items-center gap-x-2 gap-y-1">
      <p class="flex-auto py-0.5 text-xs/5 text-gray-500 dark:text-gray-400">
        <i18n-t
          :keypath="
            comment.is_internal
              ? ':name added an internal note'
              : ':name commented'
          "
          tag="span"
        >
          <template #name>
            <span class="font-medium text-gray-900 dark:text-gray-100">
              {{ comment.author_name ?? $t('Unknown') }}
            </span>
          </template>
        </i18n-t>
        <Pill v-if="comment.is_internal" color="yellow" class="ml-1">
          {{ $t('Internal') }}
        </Pill>
        <span
          v-if="comment.edited_at"
          class="text-gray-400 dark:text-gray-500"
          :title="formatDateTime(comment.edited_at)"
        >
          {{ $t('(edited)') }}
        </span>
      </p>
      <RelativeTime
        :datetime="comment.created_at"
        class="flex-none py-0.5 text-xs/5 text-gray-500 dark:text-gray-400"
      />
      <span v-if="!editing && (canEdit || canDelete)" class="flex gap-1">
        <AppButton
          v-if="canEdit"
          type="button"
          size="xs"
          color="white"
          @click="startEditing"
        >
          {{ $t('Edit') }}
        </AppButton>
        <AppButton
          v-if="canDelete"
          type="button"
          size="xs"
          color="white"
          @click="deleting = true"
        >
          {{ $t('Delete') }}
        </AppButton>
      </span>
    </header>

    <form v-if="editing" class="mt-2 space-y-2" @submit.prevent="save">
      <AppTextarea
        v-model="form.body"
        autofocus
        :aria-label="$t('Comment')"
        :has-error="!!form.errors.body"
      />
      <FieldError v-if="form.errors.body">{{ form.errors.body }}</FieldError>
      <div class="flex gap-2">
        <AppButton type="submit" size="sm" :loading="form.processing">
          {{ $t('Save') }}
        </AppButton>
        <AppButton
          type="button"
          size="sm"
          color="white"
          @click="editing = false"
        >
          {{ $t('Cancel') }}
        </AppButton>
      </div>
    </form>
    <p
      v-else
      class="mt-1 text-sm/6 whitespace-pre-wrap text-gray-700 dark:text-gray-300"
    >
      {{ comment.body }}
    </p>

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
      <template #headline>{{ $t('Delete this comment?') }}</template>
      {{ $t('The comment and its files will no longer show on this ticket.') }}
      <template #actionText>{{ $t('Delete comment') }}</template>
    </ConfirmationModal>
  </article>
</template>
