<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import {
  CheckboxField,
  DatePickerField,
  InputField,
} from '@/components/forms/fields';
import { formatDateTime, fromLocalDateTime } from '@/lib/dates';
import { destroy } from '@/routes/reminders';
import { store } from '@/routes/tickets/reminders';

const { ticketId, canDeleteTicket } = defineProps<{
  ticketId: string;
  reminders: App.Data.Tickets.TicketReminderData[];
  editable: boolean;
  canDeleteTicket: boolean;
}>();

const page = usePage();
const adding = ref(false);
const cancelling = ref<string | null>(null);

const form = useForm({
  remind_at: null as string | null,
  note: '',
  for_assignees: false,
});

/** Mirrors `TicketReminderPolicy@delete`. */
function canCancel(reminder: App.Data.Tickets.TicketReminderData): boolean {
  return (
    reminder.created_by === page.props.auth.user.id ||
    (reminder.for_assignees && canDeleteTicket)
  );
}

function add(): void {
  form
    .transform((data) => ({
      ...data,
      remind_at: fromLocalDateTime(data.remind_at),
    }))
    .submit(store(ticketId), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        adding.value = false;
      },
    });
}

function cancel(reminder: App.Data.Tickets.TicketReminderData): void {
  router.delete(destroy(reminder.id).url, {
    preserveScroll: true,
    onStart: () => (cancelling.value = reminder.id),
    onFinish: () => (cancelling.value = null),
  });
}
</script>

<template>
  <CardWrapper>
    <CardPadding class="space-y-3 text-sm">
      <div class="flex items-center justify-between">
        <h2 class="font-medium">{{ $t('Reminders') }}</h2>
        <AppButton
          v-if="editable && !adding"
          type="button"
          size="xs"
          color="white"
          @click="adding = true"
        >
          {{ $t('Add') }}
        </AppButton>
      </div>

      <ul v-if="reminders.length" class="space-y-2">
        <li
          v-for="reminder in reminders"
          :key="reminder.id"
          class="flex items-start gap-2"
        >
          <div class="flex-1">
            <p>{{ formatDateTime(reminder.remind_at) }}</p>
            <p class="text-gray-500 dark:text-gray-400">
              {{ reminder.for_assignees ? $t('For assignees') : $t('For you') }}
              <template v-if="reminder.note">· {{ reminder.note }}</template>
            </p>
          </div>
          <button
            v-if="canCancel(reminder)"
            type="button"
            class="shrink-0 rounded p-0.5 text-gray-400 hover:text-red-600 disabled:opacity-50"
            :disabled="cancelling === reminder.id"
            @click="cancel(reminder)"
          >
            <span class="sr-only">{{ $t('Cancel reminder') }}</span>
            <X class="size-4" aria-hidden="true" />
          </button>
        </li>
      </ul>
      <p v-else-if="!adding" class="text-gray-400">{{ $t('None') }}</p>

      <form v-if="adding" class="pt-1" @submit.prevent="add">
        <AppFieldset>
          <DatePickerField
            v-model="form.remind_at"
            mode="datetime"
            :label="$t('Remind at')"
            :error="form.errors.remind_at"
            required
          />
          <InputField
            v-model="form.note"
            :label="$t('Note')"
            :error="form.errors.note"
          />
          <CheckboxField
            v-model="form.for_assignees"
            :label="$t('Remind the assignees, not just me')"
            :error="form.errors.for_assignees"
          />
          <div class="flex gap-2">
            <AppButton type="submit" size="sm" :loading="form.processing">
              {{ $t('Set reminder') }}
            </AppButton>
            <AppButton
              type="button"
              size="sm"
              color="white"
              @click="adding = false"
            >
              {{ $t('Cancel') }}
            </AppButton>
          </div>
        </AppFieldset>
      </form>
    </CardPadding>
  </CardWrapper>
</template>
