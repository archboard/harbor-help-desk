<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
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
import ReplyComposer from '@/components/tickets/ReplyComposer.vue';
import TicketAttachments from '@/components/tickets/TicketAttachments.vue';
import TicketCommentCard from '@/components/tickets/TicketCommentCard.vue';
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

const { ticket, comments, events, can } = defineProps<{
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

const page = usePage();

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

/** Mirrors `TicketCommentPolicy`: authors edit their own comments on tickets they can work. */
function canEditComment(comment: TicketComment): boolean {
  return (
    can.update &&
    canEdit.value &&
    comment.author_type === 'user' &&
    comment.author_id === page.props.auth.user.id
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
          <TicketCommentCard
            v-if="'comment' in item"
            :comment="item.comment"
            :can-edit="canEditComment(item.comment)"
            :can-delete="canEditComment(item.comment) || can.delete"
          />
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

        <ReplyComposer v-if="can.update && canEdit" :ticket-id="ticket.id" />
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

        <TicketAttachments
          :ticket-id="ticket.id"
          :attachments="attachments"
          :can-upload="can.update && canEdit"
          :can-delete-any="can.delete"
        />

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
