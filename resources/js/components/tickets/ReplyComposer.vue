<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Paperclip, Upload, X } from '@lucide/vue';
import { useDropZone, useFileDialog } from '@vueuse/core';
import { computed, ref, useTemplateRef } from 'vue';
import AppButton from '@/components/AppButton.vue';
import AppTextarea from '@/components/forms/AppTextarea.vue';
import FieldError from '@/components/forms/FieldError.vue';
import FadeIn from '@/components/transitions/FadeIn.vue';
import { fileSize, firstListError } from '@/lib/files';
import { store } from '@/routes/tickets/comments';

const { ticketId } = defineProps<{ ticketId: string }>();

const form = useForm({
  body: '',
  is_internal: false,
  attachments: [] as File[],
});

function addFiles(files: Iterable<File> | null): void {
  form.attachments = [...form.attachments, ...Array.from(files ?? [])];
}

function removeFile(index: number): void {
  form.attachments = form.attachments.filter((_, i) => i !== index);
}

const fileDialog = useFileDialog({ multiple: true, reset: true });
fileDialog.onChange(addFiles);

/**
 * Show the drop target while files (not text or links) are dragged anywhere over the page.
 * `isOverDropZone` counts enters and leaves, so moving between elements doesn't flicker;
 * `onLeave` fires on every element boundary, so it's not used. Dropping outside the
 * composer is swallowed so the browser doesn't open the file.
 */
const draggedFiles = ref(false);

const { isOverDropZone: isOverPage } = useDropZone(document.body, {
  onEnter: (_, event) =>
    (draggedFiles.value = event.dataTransfer?.types.includes('Files') ?? false),
});

const draggingFiles = computed(() => isOverPage.value && draggedFiles.value);

const composer = useTemplateRef<HTMLElement>('composer');
const { isOverDropZone: isOverComposer } = useDropZone(composer, addFiles);

function send(): void {
  form.submit(store(ticketId), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
}
</script>

<template>
  <form
    ref="composer"
    :class="[
      'relative space-y-3 rounded-lg p-3 ring-1 ring-inset',
      form.is_internal
        ? 'bg-yellow-50 ring-yellow-300 dark:bg-yellow-950 dark:ring-yellow-800'
        : 'bg-white ring-gray-300 dark:bg-gray-900 dark:ring-gray-700',
    ]"
    @submit.prevent="send"
  >
    <div class="flex gap-1" role="radiogroup" aria-label="Message type">
      <button
        v-for="option in [
          { internal: false, label: 'Reply' },
          { internal: true, label: 'Internal note' },
        ]"
        :key="option.label"
        type="button"
        role="radio"
        :aria-checked="form.is_internal === option.internal"
        :class="[
          'rounded-md px-3 py-1.5 text-sm font-medium',
          form.is_internal === option.internal
            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
        ]"
        @click="form.is_internal = option.internal"
      >
        {{ option.label }}
      </button>
    </div>

    <div class="space-y-1">
      <AppTextarea
        v-model="form.body"
        :aria-label="form.is_internal ? 'Internal note' : 'Reply'"
        :placeholder="
          form.is_internal
            ? 'Only agents can see internal notes'
            : 'Write a reply'
        "
        :has-error="!!form.errors.body"
      />
      <FieldError v-if="form.errors.body">{{ form.errors.body }}</FieldError>
    </div>

    <ul v-if="form.attachments.length" class="space-y-1 text-sm">
      <li
        v-for="(file, index) in form.attachments"
        :key="`${file.name}-${index}`"
        class="flex items-center gap-2"
      >
        <Paperclip class="size-4 shrink-0 text-gray-400" aria-hidden="true" />
        <span class="truncate">{{ file.name }}</span>
        <span class="shrink-0 text-gray-400">({{ fileSize(file.size) }})</span>
        <button
          type="button"
          class="shrink-0 rounded p-0.5 text-gray-400 hover:text-red-600"
          @click="removeFile(index)"
        >
          <span class="sr-only">Remove {{ file.name }}</span>
          <X class="size-4" aria-hidden="true" />
        </button>
      </li>
    </ul>
    <FieldError v-if="firstListError(form.errors, 'attachments')">
      {{ firstListError(form.errors, 'attachments') }}
    </FieldError>

    <div class="flex items-center justify-between gap-3">
      <AppButton
        type="button"
        size="sm"
        color="white"
        @click="fileDialog.open()"
      >
        <Paperclip class="mr-1 size-4" aria-hidden="true" />
        Attach files
      </AppButton>
      <AppButton type="submit" :loading="form.processing">
        {{ form.is_internal ? 'Add note' : 'Send reply' }}
      </AppButton>
    </div>

    <FadeIn>
      <div
        v-if="draggingFiles"
        :class="[
          'pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed text-sm font-medium backdrop-blur-sm',
          isOverComposer
            ? 'border-primary-500 bg-primary-50/90 text-primary-700 dark:bg-primary-950/90 dark:text-primary-200'
            : 'border-gray-300 bg-white/80 text-gray-600 dark:border-gray-600 dark:bg-gray-900/80 dark:text-gray-300',
        ]"
      >
        <Upload class="size-6" aria-hidden="true" />
        Drop files to attach them
      </div>
    </FadeIn>
  </form>
</template>
