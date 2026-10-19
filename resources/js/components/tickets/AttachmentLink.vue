<script setup lang="ts">
import { Paperclip } from '@lucide/vue';
import { fileSize } from '@/lib/files';
import type { Attachment } from '@/types';

defineProps<{ attachment: Attachment }>();
</script>

<template>
  <!-- A plain link: the download is a file response, not an Inertia page. -->
  <a
    :href="attachment.download_url"
    class="inline-flex min-w-0 items-center gap-1 text-primary-600 hover:underline dark:text-primary-400"
  >
    <Paperclip class="size-4 shrink-0" aria-hidden="true" />
    <span class="truncate">{{ attachment.filename }}</span>
    <span class="shrink-0 text-gray-400"
      >({{ fileSize(attachment.size) }})</span
    >
  </a>
</template>
