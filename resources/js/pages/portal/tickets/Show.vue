<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import { RadioField, TextareaField } from '@/components/forms/fields';
import FieldError from '@/components/forms/FieldError.vue';
import FilePicker from '@/components/forms/FilePicker.vue';
import Pill from '@/components/Pill.vue';
import RelativeTime from '@/components/RelativeTime.vue';
import AttachmentLink from '@/components/tickets/AttachmentLink.vue';
import StatusPill from '@/components/tickets/StatusPill.vue';
import { firstListError } from '@/lib/files';
import { index } from '@/routes/portal/tickets';
import { store } from '@/routes/portal/tickets/comments';
import { store as storeRating } from '@/routes/portal/tickets/rating';

defineOptions({ layout: { title: '' } });

const { ticket } = defineProps<{
  ticket: App.Data.Portal.PortalTicketData;
  comments: App.Data.Tickets.TicketCommentData[];
  canRate: boolean;
  options: Record<string, string>;
}>();

const changing = ref(false);
const ratingForm = useForm({
  rating: ticket.satisfaction_rating?.rating ?? '',
  comment: ticket.satisfaction_rating?.comment ?? '',
});

function rate(): void {
  ratingForm.submit(storeRating(ticket.id), {
    preserveScroll: true,
    onSuccess: () => (changing.value = false),
  });
}

const form = useForm({
  body: '',
  attachments: [] as File[],
});

function send(): void {
  form.submit(store(ticket.id), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
}
</script>

<template>
  <Head :title="`#${ticket.number} ${ticket.subject}`" />

  <div class="space-y-6">
    <div class="space-y-3">
      <Link
        :href="index()"
        class="text-sm text-gray-500 hover:underline dark:text-gray-400"
      >
        ← Your tickets
      </Link>
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <h1 class="text-2xl font-semibold tracking-tight">
            {{ ticket.subject }}
          </h1>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            #{{ ticket.number }} · {{ ticket.desk_name }} · Opened
            <RelativeTime :datetime="ticket.created_at" />
          </p>
        </div>
        <StatusPill :status="ticket.status" :label="ticket.status_label" />
      </div>
    </div>

    <ul class="space-y-4">
      <li
        v-for="comment in comments"
        :key="comment.id"
        class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
      >
        <div
          class="mb-2 flex items-center justify-between gap-4 text-sm text-gray-500 dark:text-gray-400"
        >
          <span class="font-medium text-gray-900 dark:text-gray-100">
            {{ comment.author_name }}
          </span>
          <RelativeTime :datetime="comment.created_at" />
        </div>
        <p class="whitespace-pre-wrap">{{ comment.body }}</p>
        <ul v-if="comment.attachments?.length" class="mt-3 space-y-1 text-sm">
          <li v-for="attachment in comment.attachments" :key="attachment.id">
            <AttachmentLink :attachment="attachment" />
          </li>
        </ul>
      </li>
    </ul>

    <div
      v-if="canRate"
      class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
    >
      <div v-if="ticket.satisfaction_rating && !changing" class="space-y-2">
        <p class="text-sm">
          You rated this support
          <Pill
            :color="
              ticket.satisfaction_rating.rating === 'good' ? 'green' : 'red'
            "
          >
            {{ ticket.satisfaction_rating.rating_label }}
          </Pill>
          <button
            type="button"
            class="ml-2 text-primary-600 hover:underline dark:text-primary-400"
            @click="changing = true"
          >
            Change
          </button>
        </p>
        <p
          v-if="ticket.satisfaction_rating.comment"
          class="text-sm whitespace-pre-wrap"
        >
          {{ ticket.satisfaction_rating.comment }}
        </p>
      </div>
      <form v-else class="space-y-3" @submit.prevent="rate">
        <RadioField
          v-model="ratingForm.rating"
          label="How was the support you received?"
          :options="options"
          :error="ratingForm.errors.rating"
        />
        <TextareaField
          v-model="ratingForm.comment"
          label="Anything to add? (optional)"
          :error="ratingForm.errors.comment"
        />
        <AppButton type="submit" :loading="ratingForm.processing">
          {{
            ticket.satisfaction_rating ? 'Update my rating' : 'Send my rating'
          }}
        </AppButton>
      </form>
    </div>

    <form class="space-y-3" @submit.prevent="send">
      <TextareaField
        v-model="form.body"
        label="Reply"
        :error="form.errors.body"
        required
      />
      <FieldError v-if="firstListError(form.errors, 'attachments')">
        {{ firstListError(form.errors, 'attachments') }}
      </FieldError>
      <div class="flex items-start justify-between gap-4">
        <FilePicker v-model="form.attachments" />
        <AppButton type="submit" :loading="form.processing"
          >Send reply</AppButton
        >
      </div>
    </form>
  </div>
</template>
