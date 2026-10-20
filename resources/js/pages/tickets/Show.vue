<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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
import TicketAttachments from '@/components/tickets/TicketAttachments.vue';
import TicketProperties from '@/components/tickets/TicketProperties.vue';
import TicketTimeline from '@/components/tickets/TicketTimeline.vue';
import TicketReminders from '@/components/tickets/TicketReminders.vue';
import {
  formatDateTime,
  fromLocalDateTime,
  timeAgo,
  toLocalDateTime,
} from '@/lib/dates';
import { destroy, index, restore, update } from '@/routes/tickets';

defineOptions({ layout: { title: '' } });

const { ticket, comments, events, can } = defineProps<{
  ticket: App.Data.Tickets.TicketData;
  comments: App.Data.Tickets.TicketCommentData[];
  events: App.Data.Tickets.TicketEventData[];
  attachments: App.Data.Tickets.AttachmentData[];
  reminders: App.Data.Tickets.TicketReminderData[];
  deskMembers: App.Data.Desks.DeskMemberData[];
  desks: App.Data.Desks.DeskData[];
  tags: App.Data.Tags.TagData[];
  statuses: Record<string, string>;
  priorities: Record<string, string>;
  can: { update: boolean; delete: boolean; restore: boolean };
}>();

const isDeleted = computed(() => ticket.deleted_at !== null);
const canEdit = computed(() => !isDeleted.value);

const editingSubject = ref(false);
const subjectForm = useForm({
  subject: ticket.subject,
} satisfies App.Data.Tickets.UpdateTicketData);

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
      <section class="lg:col-span-2" aria-label="Activity">
        <TicketTimeline
          :ticket-id="ticket.id"
          :comments="comments"
          :events="events"
          :can-work="can.update && canEdit"
          :can-delete-any="can.delete"
        />
      </section>

      <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">
        <TicketProperties
          :key="ticket.desk_id"
          :ticket="ticket"
          :desks="desks"
          :desk-members="deskMembers"
          :tags="tags"
          :editable="can.update && canEdit"
        />
        <TicketReminders
          :ticket-id="ticket.id"
          :reminders="reminders"
          :editable="can.update && canEdit"
          :can-delete-ticket="can.delete"
        />

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
