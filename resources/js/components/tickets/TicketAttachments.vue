<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Paperclip, X } from '@lucide/vue';
import { useFileDialog } from '@vueuse/core';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import FieldError from '@/components/forms/FieldError.vue';
import AttachmentLink from '@/components/tickets/AttachmentLink.vue';
import { destroy } from '@/routes/attachments';
import { store } from '@/routes/tickets/attachments';

const { ticketId, canDeleteAny } = defineProps<{
  ticketId: string;
  attachments: App.Data.Tickets.AttachmentData[];
  canUpload: boolean;
  /** App.Data.Tickets.TicketData deleters can remove anyone's file; others only their own. */
  canDeleteAny: boolean;
}>();

const page = usePage();
const form = useForm({ file: null as File | null });
const removing = ref<string | null>(null);

function canRemove(attachment: App.Data.Tickets.AttachmentData): boolean {
  return (
    canDeleteAny ||
    (attachment.uploader_type === 'user' &&
      attachment.uploader_id === page.props.auth.user.id)
  );
}

const fileDialog = useFileDialog({ multiple: false, reset: true });

fileDialog.onChange((files) => {
  form.file = files?.[0] ?? null;

  if (form.file) {
    form.submit(store(ticketId), {
      preserveScroll: true,
      onFinish: () => form.reset(),
    });
  }
});

function remove(attachment: App.Data.Tickets.AttachmentData): void {
  router.delete(destroy(attachment.id).url, {
    preserveScroll: true,
    onStart: () => (removing.value = attachment.id),
    onFinish: () => (removing.value = null),
  });
}
</script>

<template>
  <CardWrapper v-if="attachments.length || canUpload">
    <CardPadding class="space-y-2 text-sm">
      <h2 class="font-medium">{{ $t('Attachments') }}</h2>
      <ul v-if="attachments.length" class="space-y-1">
        <li
          v-for="attachment in attachments"
          :key="attachment.id"
          class="flex items-center gap-2"
        >
          <AttachmentLink :attachment="attachment" class="flex-1" />
          <button
            v-if="canRemove(attachment)"
            type="button"
            class="shrink-0 rounded p-0.5 text-gray-400 hover:text-red-600 disabled:opacity-50"
            :disabled="removing === attachment.id"
            @click="remove(attachment)"
          >
            <span class="sr-only">{{
              $t('Remove :name', { name: attachment.filename })
            }}</span>
            <X class="size-4" aria-hidden="true" />
          </button>
        </li>
      </ul>
      <p v-else class="text-gray-400">{{ $t('None') }}</p>

      <div v-if="canUpload" class="space-y-1 pt-1">
        <AppButton
          type="button"
          size="sm"
          color="white"
          :loading="form.processing"
          @click="fileDialog.open()"
        >
          <Paperclip class="mr-1 size-4" aria-hidden="true" />
          {{ $t('Attach a file') }}
        </AppButton>
        <FieldError v-if="form.errors.file">{{ form.errors.file }}</FieldError>
      </div>
    </CardPadding>
  </CardWrapper>
</template>
