<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Paperclip } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import SimpleAlert from '@/components/alerts/SimpleAlert.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppDatepicker from '@/components/forms/AppDatepicker.vue';
import AppInput from '@/components/forms/AppInput.vue';
import AppLabel from '@/components/forms/AppLabel.vue';
import AppSelect from '@/components/forms/AppSelect.vue';
import FieldError from '@/components/forms/FieldError.vue';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import Pill from '@/components/Pill.vue';
import {
  formatDateTime,
  fromLocalDateTime,
  timeAgo,
  toLocalDateTime,
} from '@/lib/dates';
import { destroy, index, restore, update } from '@/routes/tickets';
import type {
  Attachment,
  Desk,
  DeskMember,
  Ticket,
  TicketComment,
  TicketEvent,
  TicketReminder,
} from '@/types';

defineOptions({ layout: { title: '' } });

const { ticket, comments, events } = defineProps<{
  ticket: Ticket;
  comments: TicketComment[];
  events: TicketEvent[];
  attachments: Attachment[];
  reminders: TicketReminder[];
  deskMembers: DeskMember[];
  desks: Desk[];
  statuses: Record<string, string>;
  priorities: Record<string, string>;
  can: { update: boolean; delete: boolean; restore: boolean };
}>();

const isDeleted = computed(() => ticket.deleted_at !== null);
const canEdit = computed(() => !isDeleted.value);

/** Comments and history in the order they happened. */
const thread = computed(() =>
  [
    ...comments.map((comment) => ({
      key: comment.id,
      at: comment.created_at ?? '',
      comment,
    })),
    ...events.map((event) => ({ key: event.id, at: event.created_at, event })),
  ].sort((a, b) => a.at.localeCompare(b.at)),
);

const editingSubject = ref(false);
const subjectForm = useForm({ subject: ticket.subject });

function saveSubject(): void {
  subjectForm.submit(update(ticket.id), {
    preserveScroll: true,
    onSuccess: () => (editingSubject.value = false),
  });
}

function cancelSubject(): void {
  subjectForm.reset();
  subjectForm.clearErrors();
  editingSubject.value = false;
}

const fieldErrors = ref<Record<string, string>>({});

function updateField(data: Record<string, string | null>): void {
  router.patch(update(ticket.id).url, data, {
    preserveScroll: true,
    onSuccess: () => (fieldErrors.value = {}),
    onError: (errors) => (fieldErrors.value = errors),
  });
}

const dueAt = computed(() => toLocalDateTime(ticket.due_at));

const deleting = ref(false);
const deleteLoading = ref(false);
const restoring = ref(false);

function deleteTicket(close: () => void): void {
  router.delete(destroy(ticket.id).url, {
    onStart: () => (deleteLoading.value = true),
    onFinish: () => (deleteLoading.value = false),
    onSuccess: close,
  });
}

function restoreTicket(): void {
  router.post(
    restore(ticket.id).url,
    {},
    {
      preserveScroll: true,
      onStart: () => (restoring.value = true),
      onFinish: () => (restoring.value = false),
    },
  );
}

function customFieldValue(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No';
  }

  return Array.isArray(value) ? value.join(', ') : String(value);
}

function fileSize(bytes: number): string {
  return bytes < 1024 * 1024
    ? `${Math.max(1, Math.round(bytes / 1024))} KB`
    : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
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
        ← Tickets
      </Link>

      <form
        v-if="editingSubject"
        class="space-y-1"
        @submit.prevent="saveSubject"
      >
        <div class="flex flex-col gap-2 sm:flex-row">
          <AppInput
            v-model="subjectForm.subject"
            autofocus
            aria-label="Subject"
            :has-error="!!subjectForm.errors.subject"
            class="text-lg"
          />
          <div class="flex shrink-0 gap-2">
            <AppButton type="submit" :loading="subjectForm.processing">
              Save
            </AppButton>
            <AppButton type="button" color="white" @click="cancelSubject">
              Cancel
            </AppButton>
          </div>
        </div>
        <FieldError v-if="subjectForm.errors.subject">
          {{ subjectForm.errors.subject }}
        </FieldError>
      </form>
      <div v-else class="flex items-start gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">
          <span class="text-gray-400">#{{ ticket.number }}</span>
          {{ ticket.subject }}
        </h1>
        <AppButton
          v-if="can.update && canEdit"
          type="button"
          size="xs"
          color="white"
          class="mt-1.5 shrink-0"
          @click="editingSubject = true"
        >
          Edit
        </AppButton>
      </div>

      <SimpleAlert v-if="isDeleted" level="warning">
        This ticket was deleted {{ timeAgo(ticket.deleted_at) }}.
        <AppButton
          v-if="can.restore"
          type="button"
          size="xs"
          color="white"
          class="ml-2"
          :loading="restoring"
          @click="restoreTicket"
        >
          Restore
        </AppButton>
      </SimpleAlert>

      <div class="grid gap-3 sm:grid-cols-3">
        <div class="space-y-1">
          <AppLabel for="ticket-status">Status</AppLabel>
          <AppSelect
            id="ticket-status"
            :model-value="ticket.status"
            :options="statuses"
            hide-null
            :disabled="!can.update || !canEdit"
            :has-error="!!fieldErrors.status"
            @update:model-value="updateField({ status: String($event) })"
          />
          <FieldError v-if="fieldErrors.status">
            {{ fieldErrors.status }}
          </FieldError>
        </div>
        <div class="space-y-1">
          <AppLabel for="ticket-priority">Priority</AppLabel>
          <AppSelect
            id="ticket-priority"
            :model-value="ticket.priority"
            :options="priorities"
            hide-null
            :disabled="!can.update || !canEdit"
            :has-error="!!fieldErrors.priority"
            @update:model-value="updateField({ priority: String($event) })"
          />
          <FieldError v-if="fieldErrors.priority">
            {{ fieldErrors.priority }}
          </FieldError>
        </div>
        <div class="space-y-1">
          <AppLabel for="ticket-due">Due</AppLabel>
          <AppDatepicker
            id="ticket-due"
            mode="datetime"
            :model-value="dueAt"
            :disabled="!can.update || !canEdit"
            :has-error="!!fieldErrors.due_at"
            @update:model-value="
              updateField({ due_at: fromLocalDateTime($event ?? null) })
            "
          />
          <FieldError v-if="fieldErrors.due_at">
            {{ fieldErrors.due_at }}
          </FieldError>
        </div>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <section class="space-y-4 lg:col-span-2" aria-label="Conversation">
        <template v-for="item in thread" :key="item.key">
          <article
            v-if="'comment' in item"
            :class="[
              'rounded-2xl p-5 shadow-sm',
              item.comment.is_internal
                ? 'bg-yellow-50 ring-1 ring-yellow-200 dark:bg-yellow-950 dark:ring-yellow-900'
                : 'bg-white dark:bg-gray-900',
            ]"
          >
            <header class="mb-2 flex flex-wrap items-center gap-2 text-sm">
              <span class="font-medium">
                {{ item.comment.author_name ?? 'Unknown' }}
              </span>
              <Pill v-if="item.comment.is_internal" color="yellow">
                Internal
              </Pill>
              <span
                class="text-gray-500 dark:text-gray-400"
                :title="formatDateTime(item.comment.created_at)"
              >
                {{ timeAgo(item.comment.created_at) }}
              </span>
              <span
                v-if="item.comment.edited_at"
                class="text-gray-400 dark:text-gray-500"
              >
                (edited)
              </span>
            </header>
            <p class="text-sm whitespace-pre-wrap">{{ item.comment.body }}</p>
            <ul
              v-if="item.comment.attachments?.length"
              class="mt-3 space-y-1 text-sm text-gray-600 dark:text-gray-300"
            >
              <li
                v-for="attachment in item.comment.attachments"
                :key="attachment.id"
                class="flex items-center gap-1"
              >
                <Paperclip class="size-4 shrink-0" aria-hidden="true" />
                {{ attachment.filename }}
                <span class="text-gray-400">
                  ({{ fileSize(attachment.size) }})
                </span>
              </li>
            </ul>
          </article>
          <p
            v-else
            class="px-5 text-sm text-gray-500 dark:text-gray-400"
            :title="formatDateTime(item.event.created_at)"
          >
            {{ item.event.description }}
            <span class="text-gray-400 dark:text-gray-500">
              · {{ timeAgo(item.event.created_at) }}
            </span>
          </p>
        </template>
      </section>

      <aside class="space-y-4">
        <CardWrapper>
          <CardPadding class="space-y-4 text-sm">
            <div>
              <h2 class="font-medium text-gray-500 dark:text-gray-400">
                Requester
              </h2>
              <p class="mt-1">{{ ticket.requester?.name }}</p>
              <p class="text-gray-500 dark:text-gray-400">
                {{ ticket.requester?.email }}
              </p>
            </div>
            <div>
              <h2 class="font-medium text-gray-500 dark:text-gray-400">Desk</h2>
              <p class="mt-1">{{ ticket.desk?.name }}</p>
            </div>
            <div>
              <h2 class="font-medium text-gray-500 dark:text-gray-400">
                Assignees
              </h2>
              <p class="mt-1">
                {{
                  ticket.assignees?.map((agent) => agent.name).join(', ') ||
                  'Unassigned'
                }}
              </p>
            </div>
            <div>
              <h2 class="font-medium text-gray-500 dark:text-gray-400">Tags</h2>
              <div v-if="ticket.tags?.length" class="mt-1 flex flex-wrap gap-1">
                <Pill v-for="tag in ticket.tags" :key="tag.id">
                  {{ tag.name }}
                </Pill>
              </div>
              <p v-else class="mt-1 text-gray-400">None</p>
            </div>
          </CardPadding>
        </CardWrapper>

        <CardWrapper v-if="ticket.desk?.custom_fields?.length">
          <CardPadding class="space-y-3 text-sm">
            <h2 class="font-medium">Custom fields</h2>
            <dl class="space-y-2">
              <div v-for="field in ticket.desk.custom_fields" :key="field.id">
                <dt class="text-gray-500 dark:text-gray-400">
                  {{ field.name }}
                </dt>
                <dd>{{ customFieldValue(ticket.custom_fields[field.id]) }}</dd>
              </div>
            </dl>
          </CardPadding>
        </CardWrapper>

        <CardWrapper>
          <CardPadding class="space-y-2 text-sm">
            <h2 class="font-medium">Reminders</h2>
            <ul v-if="reminders.length" class="space-y-2">
              <li v-for="reminder in reminders" :key="reminder.id">
                <p>{{ formatDateTime(reminder.remind_at) }}</p>
                <p class="text-gray-500 dark:text-gray-400">
                  {{ reminder.for_assignees ? 'For assignees' : 'For you' }}
                  <template v-if="reminder.note">
                    · {{ reminder.note }}
                  </template>
                </p>
              </li>
            </ul>
            <p v-else class="text-gray-400">None</p>
          </CardPadding>
        </CardWrapper>

        <CardWrapper v-if="attachments.length">
          <CardPadding class="space-y-2 text-sm">
            <h2 class="font-medium">Attachments</h2>
            <ul class="space-y-1">
              <li v-for="attachment in attachments" :key="attachment.id">
                {{ attachment.filename }}
                <span class="text-gray-400">
                  ({{ fileSize(attachment.size) }})
                </span>
              </li>
            </ul>
          </CardPadding>
        </CardWrapper>

        <AppButton
          v-if="can.delete && !isDeleted"
          type="button"
          color="red"
          full
          @click="deleting = true"
        >
          Delete ticket
        </AppButton>
      </aside>
    </div>
  </div>

  <ConfirmationModal
    v-if="deleting"
    :loading="deleteLoading"
    @close="deleting = false"
    @confirmed="deleteTicket"
  >
    <template #headline>Delete ticket #{{ ticket.number }}?</template>
    It moves to the Deleted list, where a desk lead can restore it.
    <template #actionText>Delete ticket</template>
  </ConfirmationModal>
</template>
