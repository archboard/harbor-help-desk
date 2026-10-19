<script setup lang="ts">
import { Paperclip, X } from '@lucide/vue';
import { useFileDialog } from '@vueuse/core';
import AppButton from '@/components/AppButton.vue';
import { fileSize } from '@/lib/files';

/**
 * Files to upload with a form: an "Attach files" button and the chosen files, each removable.
 * Picking more adds to the list rather than replacing it.
 */
const files = defineModel<File[]>({ required: true });

const dialog = useFileDialog({ multiple: true, reset: true });
dialog.onChange((picked) => {
  files.value = [...files.value, ...Array.from(picked ?? [])];
});

function remove(index: number): void {
  files.value = files.value.filter((_, i) => i !== index);
}
</script>

<template>
  <div class="min-w-0 space-y-2">
    <ul v-if="files.length" class="space-y-1 text-sm">
      <li
        v-for="(file, index) in files"
        :key="`${file.name}-${index}`"
        class="flex items-center gap-2"
      >
        <Paperclip class="size-4 shrink-0 text-gray-400" aria-hidden="true" />
        <span class="truncate">{{ file.name }}</span>
        <span class="shrink-0 text-gray-400">({{ fileSize(file.size) }})</span>
        <button
          type="button"
          class="shrink-0 rounded p-0.5 text-gray-400 hover:text-red-600"
          @click="remove(index)"
        >
          <span class="sr-only">Remove {{ file.name }}</span>
          <X class="size-4" aria-hidden="true" />
        </button>
      </li>
    </ul>
    <AppButton type="button" size="sm" color="white" @click="dialog.open()">
      <Paperclip class="mr-1 size-4" aria-hidden="true" />
      Attach files
    </AppButton>
  </div>
</template>
